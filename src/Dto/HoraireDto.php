<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Horaires corriges par l'agent (corps JSON de POST /api/marche/{id}/horaire). Les mois ne
 * sont pas exiges hors toute l'annee : un marche ponctuel ("uniquement le 06/12/2026") n'a
 * pas de periode en mois.
 */
final readonly class HoraireDto
{
    public const array JOURS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
    public const array MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    /** @param list<string> $jours */
    public function __construct(
        #[Assert\All([new Assert\Choice(choices: self::JOURS, message: 'Jour inconnu.')])]
        #[Assert\Unique]
        public array $jours = [],

        #[Assert\Time(withSeconds: false, message: "L'heure de début doit être au format HH:MM.")]
        public ?string $heureDebut = null,

        #[Assert\Time(withSeconds: false, message: "L'heure de fin doit être au format HH:MM.")]
        public ?string $heureFin = null,

        public bool $touteAnnee = false,

        #[Assert\Choice(choices: self::MOIS, message: 'Mois de début inconnu.')]
        public ?string $moisDebut = null,

        #[Assert\Choice(choices: self::MOIS, message: 'Mois de fin inconnu.')]
        public ?string $moisFin = null,
    ) {
    }
}
