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
     * Tous les departements qui ont des fiches rattachees a une commune, avec le travail restant.
     *
     * @return list<array{codeDep: string, communes: int, total: int, aTraiter: int}>
     */
    public function statsDepartements(): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('m.codeDep AS codeDep, COUNT(DISTINCT m.codeInsee) AS communes, COUNT(m.id) AS total')
            ->addSelect('SUM(CASE WHEN m.traite = false THEN 1 ELSE 0 END) AS aTraiter')
            ->where('m.codeInsee IS NOT NULL')
            ->groupBy('m.codeDep')
            ->orderBy('m.codeDep', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $r): array => [
            'codeDep' => $r['codeDep'],
            'communes' => (int) $r['communes'],
            'total' => (int) $r['total'],
            'aTraiter' => (int) $r['aTraiter'],
        ], $rows);
    }

    /** @return list<string> codes INSEE des communes du departement qui ont des fiches */
    public function communesDuDepartement(string $codeDep): array
    {
        return $this->createQueryBuilder('m')
            ->select('DISTINCT m.codeInsee')
            ->where('m.codeDep = :dep')
            ->andWhere('m.codeInsee IS NOT NULL')
            ->setParameter('dep', $codeDep)
            ->orderBy('m.codeInsee')
            ->getQuery()
            ->getSingleColumnResult();
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

    /** @return list<Marche> fiches d'un groupe de doublons, la plus ancienne d'abord */
    public function findByCodens(string $codens): array
    {
        return $this->findBy(['codens' => $codens], ['id' => 'ASC']);
    }

    /**
     * Fiches que l'agent peut lier a $marche : meme departement, dans ses communes, qui ont
     * le meme code postal (champ "cp") ou le meme nom de commune (champ "commune") que la
     * fiche, ou la meme denomination normalisee (champ "denomination", repli sur le nom brut
     * si la fiche n'a pas de denomination normalisee). Les fiches deja traitees et celles du
     * groupe de $marche sont exclues.
     *
     * @param 'cp'|'commune'|'denomination' $champ
     *
     * @return list<Marche>
     */
    public function candidatsDoublon(Marche $marche, int $userId, string $champ, int $limite = 2000): array
    {
        if ('cp' === $champ) {
            [$valeur, $colonne] = [$marche->getCodePostal(), 'm.codePostal'];
        } elseif ('commune' === $champ) {
            [$valeur, $colonne] = [$marche->getCommune(), 'm.commune'];
        } elseif (null !== $marche->getDenominationNormalise() && '' !== trim($marche->getDenominationNormalise())) {
            [$valeur, $colonne] = [$marche->getDenominationNormalise(), 'm.denominationNormalise'];
        } else {
            [$valeur, $colonne] = [$marche->getDenomination(), 'm.denomination'];
        }
        if (null === $valeur || '' === trim($valeur)) {
            return [];
        }

        $qb = $this->createQueryBuilder('m')
            ->join(CommuneAttribution::class, 'a', 'WITH', 'a.codeDep = m.codeDep AND a.codeInsee = m.codeInsee')
            ->where('a.userId = :user')
            ->andWhere('m.codeDep = :dep')
            ->andWhere('m.id <> :id')
            ->andWhere('m.traite = false')
            ->andWhere($colonne.' = :valeur')
            ->setParameter('user', $userId)
            ->setParameter('dep', $marche->getCodeDep())
            ->setParameter('id', $marche->getId())
            ->setParameter('valeur', $valeur)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults($limite);

        if (null !== $marche->getCodens()) {
            $qb->andWhere('(m.codens IS NULL OR m.codens <> :codens)')->setParameter('codens', $marche->getCodens());
        }

        return $qb->getQuery()->getResult();
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
