<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AgentService;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Sert surtout a creer le premier administrateur ; les agents se creent ensuite depuis /admin. */
#[AsCommand(name: 'app:user:create', description: 'Crée un agent (public.user)')]
final class UserCreateCommand
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AgentService $agents,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Email de connexion')] string $email,
        #[Argument(description: 'Mot de passe')] string $password,
        #[Option(description: 'Nom affiché')] ?string $name = null,
        #[Option(description: 'Donne le rôle ROLE_ADMIN')] bool $admin = false,
    ): int {
        if ($this->users->findOneBy(['email' => $email])) {
            $io->error(\sprintf('L\'utilisateur %s existe déjà.', $email));

            return Command::FAILURE;
        }

        $user = (new User())->setEmail($email)->setName($name)->setAdmin($admin);
        $this->agents->enregistrer($user, $password);

        $io->success(\sprintf('Utilisateur %s créé (id %d).', $email, $user->getId()));

        return Command::SUCCESS;
    }
}
