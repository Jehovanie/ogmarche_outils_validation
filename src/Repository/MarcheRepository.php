<?php

namespace App\Repository;

use App\Entity\CommuneAttribution;
use App\Entity\Marche;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Marche> */
class MarcheRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Marche::class);
    }

    /**
     * Departements du perimetre d'un agent, avec le travail restant.
     *
     * @return list<array{codeDep: string, total: int, aTraiter: int}>
     */
    public function statsParDepartement(int $userId): array
    {
        return $this->statsPerimetre($userId, null, 'm.codeDep');
    }

    /**
     * Communes d'un departement attribuees a l'agent, avec le travail restant.
     *
     * @return list<array{codeDep: string, codeInsee: string, commune: string, total: int, aTraiter: int}>
     */
    public function statsParCommune(int $userId, string $codeDep): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('m.codeInsee AS codeInsee, MIN(m.commune) AS commune, COUNT(m.id) AS total')
            ->addSelect('SUM(CASE WHEN m.traite = false THEN 1 ELSE 0 END) AS aTraiter')
            ->join(CommuneAttribution::class, 'a', 'WITH', 'a.codeDep = m.codeDep AND a.codeInsee = m.codeInsee')
            ->where('a.userId = :user')
            ->andWhere('m.codeDep = :dep')
            ->setParameter('user', $userId)
            ->setParameter('dep', $codeDep)
            ->groupBy('m.codeInsee')
            ->orderBy('m.codeInsee', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $r): array => [
            'codeDep' => $codeDep,
            'codeInsee' => $r['codeInsee'],
            'commune' => $r['commune'],
            'total' => (int) $r['total'],
            'aTraiter' => (int) $r['aTraiter'],
        ], $rows);
    }

    /**
     * Prochaine fiche a valider dans une commune : ni traitee ni mise de cote,
     * la plus ancienne d'abord (meme regle que /pResto/specialDep de l'outil resto).
     */
    public function prochaineAValider(string $codeDep, string $codeInsee): ?Marche
    {
        return $this->createQueryBuilder('m')
            ->where('m.codeDep = :dep')
            ->andWhere('m.codeInsee = :insee')
            ->andWhere('m.traite = false')
            ->andWhere('m.rappel = false')
            ->setParameter('dep', $codeDep)
            ->setParameter('insee', $codeInsee)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<array{codeDep: string, total: int, aTraiter: int}>
     */
    private function statsPerimetre(int $userId, ?string $codeDep, string $groupBy): array
    {
        $qb = $this->createQueryBuilder('m')
            ->select('m.codeDep AS codeDep, COUNT(m.id) AS total')
            ->addSelect('SUM(CASE WHEN m.traite = false THEN 1 ELSE 0 END) AS aTraiter')
            ->join(CommuneAttribution::class, 'a', 'WITH', 'a.codeDep = m.codeDep AND a.codeInsee = m.codeInsee')
            ->where('a.userId = :user')
            ->setParameter('user', $userId)
            ->groupBy($groupBy)
            ->orderBy($groupBy, 'ASC');
        if (null !== $codeDep) {
            $qb->andWhere('m.codeDep = :dep')->setParameter('dep', $codeDep);
        }

        return array_map(static fn (array $r): array => [
            'codeDep' => $r['codeDep'],
            'total' => (int) $r['total'],
            'aTraiter' => (int) $r['aTraiter'],
        ], $qb->getQuery()->getArrayResult());
    }
}
