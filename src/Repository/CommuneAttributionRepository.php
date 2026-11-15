<?php

namespace App\Repository;

use App\Entity\CommuneAttribution;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CommuneAttribution> */
class CommuneAttributionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommuneAttribution::class);
    }

    public function isAttributed(int $userId, string $codeDep, string $codeInsee): bool
    {
        return null !== $this->findOneBy(['userId' => $userId, 'codeDep' => $codeDep, 'codeInsee' => $codeInsee]);
    }

    public function isDepAttributed(int $userId, string $codeDep): bool
    {
        return null !== $this->findOneBy(['userId' => $userId, 'codeDep' => $codeDep]);
    }

    /** @return list<string> */
    public function departementsDe(int $userId): array
    {
        return $this->createQueryBuilder('a')
            ->select('DISTINCT a.codeDep')
            ->where('a.userId = :user')
            ->setParameter('user', $userId)
            ->orderBy('a.codeDep')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /** @return int le nombre de communes retirees */
    public function supprimerDepartement(string $codeDep): int
    {
        return $this->createQueryBuilder('a')
            ->delete()
            ->where('a.codeDep = :dep')
            ->setParameter('dep', $codeDep)
            ->getQuery()
            ->execute();
    }
}
