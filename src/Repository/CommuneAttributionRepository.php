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
}
