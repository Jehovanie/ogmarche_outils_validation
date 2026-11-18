<?php

namespace App\Enum;

/**
 * Ce que l'agent doit valider sur une fiche (colonne action_type du CSV).
 *  - SPECIFICATION_AVALIDER : seulement les produits, la specificite et les horaires ;
 *  - EXISTANCE_AVALIDER : l'existence du marche, donc aussi nom et adresse.
 */
enum ActionType: string
{
    case Specification = 'SPECIFICATION_AVALIDER';
    case Existance = 'EXISTANCE_AVALIDER';

    public function exigeNomEtAdresse(): bool
    {
        return self::Existance === $this;
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Specification => 'Spécification à valider',
            self::Existance => 'Existence à valider',
        };
    }
}
