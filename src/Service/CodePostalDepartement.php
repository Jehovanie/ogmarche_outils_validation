<?php

namespace App\Service;

/**
 * Un code postal commence normalement par le code du departement. Quelques communes
 * ont un code postal d'un departement voisin : le resultat sert donc d'avertissement,
 * pas de blocage.
 */
final class CodePostalDepartement
{
    public static function correspond(string $codePostal, string $codeDep): bool
    {
        $prefixe = match (true) {
            \in_array($codeDep, ['2A', '2B'], true) => '20',
            str_starts_with($codeDep, '97') => '97',
            default => $codeDep,
        };

        return str_starts_with($codePostal, $prefixe);
    }
}
