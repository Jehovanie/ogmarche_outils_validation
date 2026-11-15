<?php

namespace App\Service;

use App\Dto\AdresseDto;
use App\Dto\NomDto;
use App\Dto\PositionDto;
use App\Entity\Marche;
use App\Entity\User;
use App\Repository\CommuneAttributionRepository;
use App\Repository\MarcheRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Logique de validation des fiches (equivalent de AgentService cote resto).
 * Toute ecriture verifie que la commune de la fiche est dans le perimetre de l'agent.
 *
 * Doublons : des fiches liees partagent un "codens" (voir CodensGenerator). Le groupe
 * s'affiche et se traite d'un bloc.
 */
final class AdresseValidationService
{
    public function __construct(
        private readonly MarcheRepository $marches,
        private readonly CommuneAttributionRepository $attributions,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly CodensGenerator $codens,
    ) {
    }

    /**
     * Enregistre l'adresse corrigee. Retourne les avertissements (non bloquants).
     *
     * @return list<string>
     */
    public function changerAdresse(int $id, AdresseDto $dto, User $user): array
    {
        $marche = $this->fiche($id, $user);
        $marche->changeAdresse(
            $this->vide($dto->numVoie),
            $this->vide($dto->typeVoie),
            $this->vide($dto->nomVoie),
            $this->vide($dto->codePostal),
            trim($dto->commune),
        );
        $this->em->flush();

        $avertissements = [];
        if (null !== $marche->getCodePostal() && !CodePostalDepartement::correspond($marche->getCodePostal(), $marche->getCodeDep())) {
            $avertissements[] = \sprintf('Le code postal %s ne correspond pas au département %s.', $marche->getCodePostal(), $marche->getCodeDep());
        }

        return $avertissements;
    }

    public function changerPosition(int $id, PositionDto $dto, User $user): void
    {
        $this->fiche($id, $user)->changePosition((float) $dto->latitude, (float) $dto->longitude);
        $this->em->flush();
    }

    public function changerNom(int $id, NomDto $dto, User $user): void
    {
        $this->fiche($id, $user)->changeDenomination(trim($dto->denomination));
        $this->em->flush();
    }

    public function definirNomOk(int $id, bool $ok, User $user): void
    {
        $this->fiche($id, $user)->setNomOk($ok);
        $this->em->flush();
    }

    public function definirAdresseOk(int $id, bool $ok, User $user): void
    {
        $this->fiche($id, $user)->setAdresseOk($ok);
        $this->em->flush();
    }

    /**
     * Traite la fiche, et tout son groupe de doublons le cas echeant.
     *
     * @throws \DomainException si le nom ou l'adresse d'une fiche du groupe n'est pas valide
     */
    public function marquerTraite(int $id, User $user): void
    {
        $groupe = $this->groupe($this->fiche($id, $user));
        foreach ($groupe as $marche) {
            $this->assertDansPerimetre($marche, $user);
            if (!$marche->isNomOk()) {
                throw new \DomainException(\sprintf('Le nom doit être validé avant de traiter la fiche %d.', $marche->getId()));
            }
            if (!$marche->isAdresseOk()) {
                throw new \DomainException(\sprintf("L'adresse doit être validée avant de traiter la fiche %d.", $marche->getId()));
            }
        }

        $now = $this->clock->now();
        foreach ($groupe as $marche) {
            $marche->marquerTraite((int) $user->getId(), $now);
        }
        $this->em->flush();
    }

    /**
     * Lie la fiche $id aux fiches $ids dans un meme groupe de doublons. Si certaines
     * appartiennent deja a un groupe, ces groupes sont fusionnes dans le plus ancien.
     *
     * @param list<int> $ids
     *
     * @return string le codens du groupe obtenu
     *
     * @throws \DomainException si moins de deux fiches ou departements differents
     */
    public function lier(int $id, array $ids, User $user): string
    {
        $fiches = [];
        foreach (array_unique([$id, ...$ids]) as $ficheId) {
            $fiches[$ficheId] = $this->fiche($ficheId, $user);
        }
        if (\count($fiches) < 2) {
            throw new \DomainException('Il faut au moins deux fiches différentes pour former un groupe.');
        }
        $deps = array_values(array_unique(array_map(static fn (Marche $m): string => $m->getCodeDep(), $fiches)));
        if (\count($deps) > 1) {
            throw new \DomainException('Les fiches liées doivent appartenir au même département.');
        }

        $existants = array_values(array_unique(array_filter(array_map(static fn (Marche $m): ?string => $m->getCodens(), $fiches))));
        sort($existants, \SORT_NATURAL);

        return $this->em->wrapInTransaction(function () use ($fiches, $existants, $deps, $user): string {
            $cible = $existants[0] ?? $this->codens->suivant($deps[0]);

            // les groupes fusionnes entrainent tous leurs membres
            foreach ($existants as $codens) {
                foreach ($this->marches->findByCodens($codens) as $membre) {
                    $this->assertDansPerimetre($membre, $user);
                    $membre->setCodens($cible);
                }
            }
            foreach ($fiches as $marche) {
                $marche->setCodens($cible);
            }

            return $cible;
        });
    }

    /** Retire la fiche de son groupe ; un groupe reduit a une seule fiche est dissous. */
    public function delier(int $id, User $user): void
    {
        $marche = $this->fiche($id, $user);
        $codens = $marche->getCodens();
        if (null === $codens) {
            return;
        }

        $groupe = $this->marches->findByCodens($codens);
        $marche->setCodens(null);
        if (2 === \count($groupe)) {
            foreach ($groupe as $autre) {
                $autre->setCodens(null);
            }
        }
        $this->em->flush();
    }

    /**
     * Fiches a lier proposees a l'agent, par code postal, commune ou denomination de la fiche.
     *
     * @param 'cp'|'commune'|'denomination' $champ
     *
     * @return list<Marche>
     */
    public function candidats(int $id, string $champ, User $user): array
    {
        if (!\in_array($champ, ['cp', 'commune', 'denomination'], true)) {
            throw new \InvalidArgumentException('Champ de recherche inconnu.');
        }

        return $this->marches->candidatsDoublon($this->fiche($id, $user), (int) $user->getId(), $champ);
    }

    /**
     * Fiche a afficher dans une commune, apres controle du perimetre : $ficheId si elle est
     * de cette commune et pas encore traitee, sinon la plus ancienne a valider.
     */
    public function prochaineFiche(string $codeDep, string $codeInsee, User $user, ?int $ficheId = null): ?Marche
    {
        $this->assertCommuneDansPerimetre($codeDep, $codeInsee, $user);

        if (null !== $ficheId) {
            $marche = $this->marches->find($ficheId);
            if (null !== $marche && !$marche->isTraite() && $marche->getCodeDep() === $codeDep && $marche->getCodeInsee() === $codeInsee) {
                return $marche;
            }
        }

        return $this->marches->prochaineAValider($codeDep, $codeInsee);
    }

    /** @return list<Marche> la fiche et ses doublons (elle seule si elle n'est liee a rien) */
    public function groupe(Marche $marche): array
    {
        return null === $marche->getCodens() ? [$marche] : $this->marches->findByCodens($marche->getCodens());
    }

    public function assertCommuneDansPerimetre(string $codeDep, string $codeInsee, User $user): void
    {
        if (!$this->attributions->isAttributed((int) $user->getId(), $codeDep, $codeInsee)) {
            throw new AccessDeniedException('Cette commune ne vous est pas attribuée.');
        }
    }

    private function assertDansPerimetre(Marche $marche, User $user): void
    {
        if (null === $marche->getCodeInsee()) {
            throw new AccessDeniedException("Cette fiche n'appartient à aucune commune attribuée.");
        }
        $this->assertCommuneDansPerimetre($marche->getCodeDep(), $marche->getCodeInsee(), $user);
    }

    private function fiche(int $id, User $user): Marche
    {
        $marche = $this->marches->find($id) ?? throw new NotFoundHttpException('Fiche introuvable.');
        $this->assertDansPerimetre($marche, $user);

        return $marche;
    }

    private function vide(?string $valeur): ?string
    {
        $valeur = null === $valeur ? null : trim($valeur);

        return '' === $valeur ? null : $valeur;
    }
}
