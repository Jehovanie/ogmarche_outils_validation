<?php

namespace App\Service;

use App\Entity\CommuneAttribution;
use App\Entity\User;
use App\Repository\CommuneAttributionRepository;
use App\Repository\MarcheRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Distribution des departements aux agents. Un departement appartient a un seul agent :
 * l'attribuer donne a l'agent toutes ses communes et les retire a l'agent precedent.
 * Le perimetre reste stocke commune par commune (CommuneAttribution), que le parcours de
 * validation controle a chaque action.
 */
final class AttributionService
{
    private MarcheRepository $marches;
    private CommuneAttributionRepository $attributions;
    private EntityManagerInterface $em;

    public function __construct(MarcheRepository $marches, CommuneAttributionRepository $attributions, EntityManagerInterface $em)
    {
        $this->marches = $marches;
        $this->attributions = $attributions;
        $this->em = $em;
    }

    /** @return array<string, int> code de chaque departement qui a des fiches => nombre de lignes (fiches) */
    public function lignesParDepartement(): array
    {
        return array_column($this->marches->statsDepartements(), 'total', 'codeDep');
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
            static fn ($dep): bool => \in_array((string) $dep, $codesDep, true),
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
