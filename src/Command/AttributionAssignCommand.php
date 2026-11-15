<?php

namespace App\Command;

use App\Entity\CommuneAttribution;
use App\Entity\Marche;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:attribution:assign', description: 'Attribue à un agent toutes les communes d\'un département non encore attribuées')]
final class AttributionAssignCommand
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Email de l\'agent')] string $email,
        #[Argument(description: 'Code département (ex. 01)')] string $dep,
    ): int {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (null === $user) {
            $io->error(\sprintf('Utilisateur %s introuvable.', $email));

            return Command::FAILURE;
        }

        $insees = $this->em->createQueryBuilder()
            ->select('DISTINCT m.codeInsee')
            ->from(Marche::class, 'm')
            ->leftJoin(CommuneAttribution::class, 'a', 'WITH', 'a.codeDep = m.codeDep AND a.codeInsee = m.codeInsee')
            ->where('m.codeDep = :dep')
            ->andWhere('m.codeInsee IS NOT NULL')
            ->andWhere('a.id IS NULL')
            ->setParameter('dep', $dep)
            ->getQuery()
            ->getSingleColumnResult();

        foreach ($insees as $insee) {
            $this->em->persist(new CommuneAttribution($dep, $insee, (int) $user->getId()));
        }
        $this->em->flush();

        $io->success(\sprintf('%d commune(s) du département %s attribuée(s) à %s.', \count($insees), $dep, $email));

        return Command::SUCCESS;
    }
}
