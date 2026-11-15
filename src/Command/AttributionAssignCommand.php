<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\AttributionService;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:attribution:assign', description: 'Attribue un département entier à un agent (et le retire à l\'agent précédent)')]
final class AttributionAssignCommand
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AttributionService $attributions,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Email de l\'agent')] string $email,
        #[Argument(description: 'Code département (ex. 01)')] string $dep,
    ): int {
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
