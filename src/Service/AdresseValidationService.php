<?php

namespace App\Service;

use App\Dto\AdresseDto;
use App\Dto\CreneauDto;
use App\Dto\HoraireDto;
use App\Dto\NomDto;
use App\Dto\PositionDto;
use App\Dto\ProduitsDto;
use App\Dto\SpecificiteDto;
use App\Entity\Marche;
use App\Entity\MarcheHoraire;
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

    /**
     * Enregistre les produits corriges. Retourne les avertissements (non bloquants) :
     * les mentions de jour ou d'horaire qui restent dans le texte.
     *
     * @return list<string>
     */
    public function changerProduits(int $id, ProduitsDto $dto, User $user): array
    {
        $marche = $this->fiche($id, $user);
        $marche->changeProduits($this->vide($dto->produits));
        $this->em->flush();

        $avertissements = [];
        if ([] !== $mentions = MentionTemporelle::detecter($marche->getProduits())) {
            $avertissements[] = \sprintf('Mentions de temps encore présentes dans les produits : %s.', implode(', ', $mentions));
        }

        return $avertissements;
    }

    /** @return array{resume: string, avertissements: list<string>} */
    public function changerHoraire(int $id, HoraireDto $dto, User $user): array
    {
        $heure = static fn (?string $h): ?\DateTimeImmutable => null === $h || '' === $h ? null : new \DateTimeImmutable('1970-01-01 '.$h);
        $numero = array_flip(MarcheHoraire::JOURS);

        $creneaux = array_map(static fn (CreneauDto $c): array => [
            'jour' => $numero[$c->jour],
            'moment' => '' === $c->moment ? null : $c->moment,
            'debut' => $heure($c->heureDebut),
            'fin' => $heure($c->heureFin),
        ], $dto->creneaux);
        // dans l'ordre de la semaine, quel que soit l'ordre recu
        usort($creneaux, static fn (array $a, array $b): int => [$a['jour'], $a['debut']?->format('H:i')] <=> [$b['jour'], $b['debut']?->format('H:i')]);

        $marche = $this->fiche($id, $user);
        $marche->changeHoraire($creneaux, $dto->touteAnnee, $dto->moisDebut, $dto->moisFin);
        $this->em->flush();

        return ['resume' => $marche->getResumeHoraire(), 'avertissements' => $marche->getAvertissementsHoraire()];
    }

    public function definirHoraireOk(int $id, bool $ok, User $user): void
    {
        $this->fiche($id, $user)->setHoraireOk($ok);
        $this->em->flush();
    }

    public function changerSpecificite(int $id, SpecificiteDto $dto, User $user): void
    {
        $this->fiche($id, $user)->changeSpecificite($this->vide($dto->specificite));
        $this->em->flush();
    }

    public function definirSpecificiteOk(int $id, bool $ok, User $user): void
    {
        $this->fiche($id, $user)->setSpecificiteOk($ok);
        $this->em->flush();
    }

    public function definirProduitsOk(int $id, bool $ok, User $user): void
    {
        $this->fiche($id, $user)->setProduitsOk($ok);
        $this->em->flush();
    }

    /**
     * Decision de l'agent pour toute la fiche et son groupe de doublons : garder (true), ne pas garder
     * (false) ou revenir a "pas decide" (null). Elle est portee par les fiches "existence a valider" du
     * groupe ; les fiches "specification a valider" suivent la decision de l'existence.
     *
     * @throws \DomainException si le groupe ne compte aucune fiche dont l'existence est a valider
     */
    public function definirGarde(int $id, ?bool $garde, User $user): void
    {
        $groupe = $this->groupe($this->fiche($id, $user));
        $existences = array_filter($groupe, static fn (Marche $m): bool => $m->getActionType()->exigeNomEtAdresse());
        if ([] === $existences) {
            throw new \DomainException("La décision garder / ne pas garder ne concerne que les fiches dont l'existence est à valider.");
        }
        foreach ($existences as $marche) {
            $this->assertDansPerimetre($marche, $user);
            $marche->setGarde($garde);
        }
        $this->em->flush();
    }

    /**
     * Etat de la decision garder / ne pas garder d'un groupe : possible s'il compte une existence a valider,
     * et valeur commune de ces fiches (null si pas decide ou si elles divergent).
     *
     * @param list<Marche> $groupe
     *
     * @return array{possible: bool, garde: ?bool}
     */
    public function decisionGarde(array $groupe): array
    {
        $existences = array_values(array_filter($groupe, static fn (Marche $m): bool => $m->getActionType()->exigeNomEtAdresse()));
        $valeurs = array_unique(array_map(static fn (Marche $m): ?bool => $m->isGarde(), $existences), \SORT_REGULAR);

        return ['possible' => [] !== $existences, 'garde' => 1 === \count($valeurs) ? $valeurs[0] : null];
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
     * Traite la fiche, et tout son groupe de doublons le cas echeant. Chaque fiche doit avoir
     * ses horaires verifies (s'il y a un horaire_brut) et ses produits et sa specificite valides (s'il y en a) ; une fiche
     * EXISTANCE_AVALIDER doit aussi avoir son nom et son adresse, et le groupe une decision garder / ne pas garder
     * des qu'il compte une telle fiche.
     *
     * @throws \DomainException si une validation exigee manque sur une fiche du groupe
     */
    public function marquerTraite(int $id, User $user): void
    {
        $groupe = $this->groupe($this->fiche($id, $user));
        foreach ($groupe as $marche) {
            $this->assertDansPerimetre($marche, $user);
            // nom et adresse ne sont exiges que pour une existence a valider
            if ($marche->getActionType()->exigeNomEtAdresse()) {
                if (!$marche->isNomOk()) {
                    throw new \DomainException(\sprintf('Le nom doit être validé avant de traiter la fiche %d.', $marche->getId()));
                }
                if (!$marche->isAdresseOk()) {
                    throw new \DomainException(\sprintf("L'adresse doit être validée avant de traiter la fiche %d.", $marche->getId()));
                }
            }
            if ($marche->exigeValidationHoraire() && !$marche->isHoraireOk()) {
                throw new \DomainException(\sprintf('Les horaires doivent être vérifiés avant de traiter la fiche %d.', $marche->getId()));
            }
            if ($marche->exigeValidationSpecificite() && !$marche->isSpecificiteOk()) {
                throw new \DomainException(\sprintf('La spécificité doit être validée avant de traiter la fiche %d.', $marche->getId()));
            }
            if ($marche->exigeValidationProduits() && !$marche->isProduitsOk()) {
                throw new \DomainException(\sprintf('Les produits doivent être validés avant de traiter la fiche %d.', $marche->getId()));
            }
        }

        // une existence a valider (seule ou dans le groupe) exige la decision garder / ne pas garder
        $decision = $this->decisionGarde($groupe);
        if ($decision['possible'] && null === $decision['garde']) {
            throw new \DomainException('Indiquez si la fiche est à garder ou à ne pas garder avant de la traiter.');
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
