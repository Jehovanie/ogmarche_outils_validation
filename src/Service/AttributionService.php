<?php

namespace App\Service;

use App\Entity\CommuneAttribution;
use App\Entity\User;
use App\Repository\CommuneAttributionRepository;
use App\Repository\MarcheRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Distribution des departements aux agents. Un departement appartient a un seul agent :
 * l'attribuer donne a l'agent toutes ses communes et les retire a l'agent precedent.
 * Le perimetre reste stocke commune par commune (CommuneAttribution), que le parcours de
 * validation controle a chaque action.
 */
final class AttributionService
{
    public function __construct(
        private readonly MarcheRepository $marches,
        private readonly CommuneAttributionRepository $attributions,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @return array<string, int> code de chaque departement qui a des fiches => nombre de lignes (fiches) */
    public function lignesParDepartement(): array
    {
        return array_column($this->marches->statsDepartements(), 'total', 'codeDep');
    }

    /**
     * Repartition actuelle : pour chaque agent qui a des fiches attribuees, ses departements
     * avec le nombre de lignes (fiches) et ce qu'il reste a traiter. Les agents sont tries par
     * nombre de lignes decroissant.
     *
     * @return list<array{agent: User, departements: list<array{codeDep: string, communes: int, total: int, aTraiter: int}>, total: int, aTraiter: int}>
     */
    public function repartitionActuelle(): array
    {
        $parAgent = [];
        foreach ($this->marches->statsAttributions() as $ligne) {
            $parAgent[$ligne['userId']][] = $ligne;
        }
        if ([] === $parAgent) {
            return [];
        }

        $repartition = [];
        foreach ($this->users->findBy(['id' => array_keys($parAgent)]) as $agent) {
            $deps = $parAgent[(int) $agent->getId()];
            $repartition[] = [
                'agent' => $agent,
                'departements' => $deps,
                'total' => array_sum(array_column($deps, 'total')),
                'aTraiter' => array_sum(array_column($deps, 'aTraiter')),
            ];
        }
        usort($repartition, static fn (array $a, array $b): int => [$b['total'], $a['agent']->getLibelle()] <=> [$a['total'], $b['agent']->getLibelle()]);

        return $repartition;
    }

    /** @return list<string> codes des departements dont au moins une commune (avec fiches) n'a pas d'agent */
    public function departementsNonAttribues(): array
    {
        $attribuees = [];
        foreach ($this->marches->statsAttributions() as $ligne) {
            $attribuees[$ligne['codeDep']] = ($attribuees[$ligne['codeDep']] ?? 0) + $ligne['communes'];
        }

        $nonAttribues = [];
        foreach ($this->marches->statsDepartements() as $dep) {
            if (($attribuees[$dep['codeDep']] ?? 0) < $dep['communes']) {
                $nonAttribues[] = (string) $dep['codeDep'];
            }
        }

        return $nonAttribues;
    }

    /** @return list<string> codes des departements dont l'agent a au moins une commune */
    public function departementsDe(User $agent): array
    {
        return $this->attributions->departementsDe((int) $agent->getId());
    }

    /** @return int le nombre de communes attribuees */
    public function attribuer(string $codeDep, User $agent): int
    {
        return $this->em->wrapInTransaction(fn (): int => $this->remplacer($codeDep, $agent));
    }

    /**
     * Repartition automatique des departements choisis entre les agents choisis, en
     * equilibrant le nombre de lignes (fiches) de chacun. Voir Repartition.
     *
     * @param list<string> $codesDep
     * @param list<User>   $agents
     *
     * @return list<array{agent: User, departements: list<string>, lignes: int}>
     */
    public function planifierRepartition(array $codesDep, array $agents): array
    {
        $lignes = array_filter(
            $this->lignesParDepartement(),
            static fn (int|string $dep): bool => \in_array((string) $dep, $codesDep, true),
            \ARRAY_FILTER_USE_KEY,
        );

        return Repartition::planifier($lignes, $agents);
    }

    /**
     * Applique une repartition en une seule transaction : tout ou rien.
     *
     * @param list<array{agent: User, departements: list<string>, lignes: int}> $plan
     */
    public function appliquerRepartition(array $plan): void
    {
        $this->em->wrapInTransaction(function () use ($plan): void {
            foreach ($plan as ['agent' => $agent, 'departements' => $deps]) {
                foreach ($deps as $dep) {
                    $this->remplacer($dep, $agent);
                }
            }
        });
    }

    /** Donne toutes les communes du departement a l'agent (a appeler dans une transaction). */
    private function remplacer(string $codeDep, User $agent): int
    {
        $insees = $this->marches->communesDuDepartement($codeDep);
        $this->attributions->supprimerDepartement($codeDep);
        foreach ($insees as $insee) {
            $this->em->persist(new CommuneAttribution($codeDep, $insee, (int) $agent->getId()));
        }

        return \count($insees);
    }
}
