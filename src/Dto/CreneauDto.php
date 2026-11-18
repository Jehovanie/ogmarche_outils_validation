<?php

namespace App\Dto;

use App\Entity\MarcheHoraire;
use Symfony\Component\Validator\Constraints as Assert;

/** Un creneau d'ouverture : un jour, et une plage horaire et/ou un moment de la journee. */
final readonly class CreneauDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Jour manquant.')]
        #[Assert\Choice(choices: MarcheHoraire::JOURS, message: 'Jour inconnu.')]
        public string $jour = '',

        #[Assert\Choice(choices: MarcheHoraire::MOMENTS, message: 'Moment de la journée inconnu.')]
        public ?string $moment = null,

        #[Assert\Time(withSeconds: false, message: "L'heure de début doit être au format HH:MM.")]
        public ?string $heureDebut = null,

        #[Assert\Time(withSeconds: false, message: "L'heure de fin doit être au format HH:MM.")]
        public ?string $heureFin = null,
    ) {
    }
}
