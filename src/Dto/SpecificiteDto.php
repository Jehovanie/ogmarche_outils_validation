<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Specificite corrigee par l'agent (corps JSON de POST /api/marche/{id}/specificite). */
final readonly class SpecificiteDto
{
    public function __construct(
        #[Assert\Length(max: 255, maxMessage: 'La spécificité ne doit pas dépasser {{ limit }} caractères.')]
        public ?string $specificite = null,
    ) {
    }
}
