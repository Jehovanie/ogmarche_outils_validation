<?php

namespace App\Service;

use App\Entity\User;

/**
 * Repartit des departements entiers entre des agents pour equilibrer le nombre de lignes
 * (fiches) de chacun : le plus gros departement restant va a l'agent le moins charge. Un
 * departement ne se coupe pas, l'equilibre est donc approche, pas exact. Le resultat est
 * deterministe (memes entrees, meme repartition) : l'apercu montre ce qui sera applique.
 */
final class Repartition
{
    /**
     * @param array<string, int> $lignesParDepartement code du departement => nombre de lignes
     * @param list<User>         $agents
     *
     * @return list<array{agent: User, departements: list<string>, lignes: int}> dans l'ordre des agents recus
     */
    public static function planifier(array $lignesParDepartement, array $agents): array
    {
        if ([] === $agents) {
            return [];
        }

        $plan = array_map(static fn (User $agent): array => ['agent' => $agent, 'departements' => [], 'lignes' => 0], $agents);

        // plus gros departement d'abord ; a egalite, ordre des codes
        // strval : PHP transforme une cle comme '99' en entier
        $deps = array_map('strval', array_keys($lignesParDepartement));
        usort($deps, static fn (string $a, string $b): int => [$lignesParDepartement[$b], $a] <=> [$lignesParDepartement[$a], $b]);

        foreach ($deps as $dep) {
            // agent le moins charge ; a egalite, le premier de la liste
            $cible = 0;
            foreach ($plan as $i => $part) {
                if ($part['lignes'] < $plan[$cible]['lignes']) {
                    $cible = $i;
                }
            }
            $plan[$cible]['departements'][] = $dep;
            $plan[$cible]['lignes'] += $lignesParDepartement[$dep];
        }

        foreach ($plan as &$part) {
            sort($part['departements'], \SORT_NATURAL);
        }

        return $plan;
    }
}
