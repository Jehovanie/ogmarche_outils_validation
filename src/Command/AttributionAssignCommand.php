<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\AttributionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:attribution:assign', description: 'Attribue un département entier à un agent (et le retire à l\'agent précédent)')]
final class AttributionAssignCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AttributionService $attributions,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de l\'agent')
            ->addArgument('dep', InputArgument::REQUIRED, 'Code département (ex. 01)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $dep = (string) $input->getArgument('dep');

        $user = $this->users->findOneBy(['email' => $email]);
        if (null === $user) {
            $io->error(\sprintf('Utilisateur %s introuvable.', $email));

            return Command::FAILURE;
        }

        $communes = $this->attributions->attribuer($dep, $user);
        $io->success(\sprintf('Département %s (%d commune(s)) attribué à %s.', $dep, $communes, $email));

        return Command::SUCCESS;
    }
}
