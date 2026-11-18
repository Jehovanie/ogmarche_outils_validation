<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Position corrigee par l'agent (corps JSON de POST /api/marche/{id}/position). */
final class PositionDto
{
    /**
     * @Assert\NotNull(message="La latitude est obligatoire.")
     * @Assert\Range(min=-90, max=90, notInRangeMessage="La latitude doit être comprise entre {{ min }} et {{ max }}.")
     */
    public ?float $latitude = null;

    /**
     * @Assert\NotNull(message="La longitude est obligatoire.")
     * @Assert\Range(min=-180, max=180, notInRangeMessage="La longitude doit être comprise entre {{ min }} et {{ max }}.")
     */
    public ?float $longitude = null;
}
