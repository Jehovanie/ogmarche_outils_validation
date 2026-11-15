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
 * Logique de validation des adresses (equivalent de AgentService cote resto).
 * Toute ecriture verifie que la commune de la fiche est dans le perimetre de l'agent.
 */
final class AdresseValidationService
{
    public function __construct(
        private readonly MarcheRepository $marches,
        private readonly CommuneAttributionRepository $attributions,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
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

    /** @throws \DomainException si le nom ou l'adresse n'a pas ete valide */
    public function marquerTraite(int $id, User $user): void
    {
        $marche = $this->fiche($id, $user);
        if (!$marche->isNomOk()) {
            throw new \DomainException("Le nom doit être validé avant de traiter la fiche.");
        }
        if (!$marche->isAdresseOk()) {
            throw new \DomainException("L'adresse doit être validée avant de traiter la fiche.");
        }
        $marche->marquerTraite((int) $user->getId(), $this->clock->now());
        $this->em->flush();
    }

    public function definirRappel(int $id, bool $rappel, User $user): void
    {
        $this->fiche($id, $user)->setRappel($rappel);
        $this->em->flush();
    }

    /** Fiche a afficher dans une commune, apres controle du perimetre. */
    public function prochaineFiche(string $codeDep, string $codeInsee, User $user): ?Marche
    {
        $this->assertCommuneDansPerimetre($codeDep, $codeInsee, $user);

        return $this->marches->prochaineAValider($codeDep, $codeInsee);
    }

    public function assertCommuneDansPerimetre(string $codeDep, string $codeInsee, User $user): void
    {
        if (!$this->attributions->isAttributed((int) $user->getId(), $codeDep, $codeInsee)) {
            throw new AccessDeniedException("Cette commune ne vous est pas attribuée.");
        }
    }

    private function fiche(int $id, User $user): Marche
    {
        $marche = $this->marches->find($id) ?? throw new NotFoundHttpException('Fiche introuvable.');
        if (null === $marche->getCodeInsee()) {
            throw new AccessDeniedException("Cette fiche n'appartient à aucune commune attribuée.");
        }
        $this->assertCommuneDansPerimetre($marche->getCodeDep(), $marche->getCodeInsee(), $user);

        return $marche;
    }

    private function vide(?string $valeur): ?string
    {
        $valeur = null === $valeur ? null : trim($valeur);

        return '' === $valeur ? null : $valeur;
    }
}
