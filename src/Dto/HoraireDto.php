<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Horaires corriges par l'agent : creneaux (un horaire propre a chaque jour) et periode (corps JSON de POST /api/marche/{id}/horaire). Les mois ne
 * sont pas exiges hors toute l'annee : un marche ponctuel ("uniquement le 06/12/2026") n'a
 * pas de periode en mois.
 */
final readonly class HoraireDto
{
    public const array MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    /** @param list<CreneauDto> $creneaux un ou plusieurs creneaux par jour ouvert */
    public function __construct(
        #[Assert\Valid]
        #[Assert\Count(max: 40, maxMessage: 'Trop de créneaux.')]
        public array $creneaux = [],

        public bool $touteAnnee = false,

        #[Assert\Choice(choices: self::MOIS, message: 'Mois de début inconnu.')]
        public ?string $moisDebut = null,

        #[Assert\Choice(choices: self::MOIS, message: 'Mois de fin inconnu.')]
        public ?string $moisFin = null,
    ) {
    }
}
