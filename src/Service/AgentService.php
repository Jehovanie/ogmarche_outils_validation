<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Enregistrement des comptes (public.user), communs a tous les outils de validation. */
final class AgentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    /** Cree ou met a jour le compte ; un mot de passe vide garde l'ancien. */
    public function enregistrer(User $user, ?string $motDePasse): void
    {
        if (null !== $motDePasse && '' !== $motDePasse) {
            $user->setPassword($this->hasher->hashPassword($user, $motDePasse));
        }
        $this->em->persist($user);
        $this->em->flush();
    }
}
