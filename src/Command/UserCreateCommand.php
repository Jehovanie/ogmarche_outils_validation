<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AgentService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Sert surtout a creer le premier administrateur ; les agents se creent ensuite depuis /admin. */
#[AsCommand(name: 'app:user:create', description: 'Crée un agent (public.user)')]
final class UserCreateCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AgentService $agents,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de connexion')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Nom affiché')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Donne le rôle ROLE_ADMIN');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $password = (string) $input->getArgument('password');
        $name = $input->getOption('name');
        $admin = (bool) $input->getOption('admin');

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
