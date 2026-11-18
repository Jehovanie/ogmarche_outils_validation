<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Produits corriges par l'agent (corps JSON de POST /api/marche/{id}/produits). */
final class ProduitsDto
{
    /** @Assert\Length(max=5000) */
    public ?string $produits = null;
}
