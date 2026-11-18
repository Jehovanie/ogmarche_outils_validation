<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Enregistrement des comptes (public.user), communs a tous les outils de validation. */
final class AgentService
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $hasher;

    public function __construct(EntityManagerInterface $em, UserPasswordHasherInterface $hasher)
    {
        $this->em = $em;
        $this->hasher = $hasher;
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
