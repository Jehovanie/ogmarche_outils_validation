<?php

namespace App\Enum;

/**
 * Ce que l'agent doit valider sur une fiche (colonne action_type du CSV).
 *  - SPECIFICATION : seulement les produits ;
 *  - EXISTANCE : l'existence du marche, donc nom, adresse et produits.
 *
 * Constantes de classe (PHP 7.4 : pas d'enum).
 */
final class ActionType
{
    public const SPECIFICATION = 'SPECIFICATION_AVALIDER';
    public const EXISTANCE = 'EXISTANCE_AVALIDER';

    public static function exigeNomEtAdresse(string $type): bool
    {
        return self::EXISTANCE === $type;
    }

    public static function libelle(string $type): string
    {
        return self::EXISTANCE === $type ? 'Existence à valider' : 'Spécification à valider';
    }

    private function __construct()
    {
    }
}
