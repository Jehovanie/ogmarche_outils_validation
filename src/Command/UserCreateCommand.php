<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:create', description: 'Crée un agent (public.user)')]
final class UserCreateCommand
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Email de connexion')] string $email,
        #[Argument(description: 'Mot de passe')] string $password,
        #[Option(description: 'Nom affiché')] ?string $name = null,
        #[Option(description: 'Donne le rôle ROLE_ADMIN')] bool $admin = false,
    ): int {
        if ($this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            $io->error(\sprintf('L\'utilisateur %s existe déjà.', $email));

            return Command::FAILURE;
        }

        $user = (new User())->setEmail($email)->setName($name)->setRoles($admin ? ['ROLE_ADMIN'] : []);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        $io->success(\sprintf('Utilisateur %s créé (id %d).', $email, $user->getId()));

        return Command::SUCCESS;
    }
}
