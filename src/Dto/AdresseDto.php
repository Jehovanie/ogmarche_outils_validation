<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Adresse corrigee par l'agent (corps JSON de POST /api/marche/{id}/adresse). */
final class AdresseDto
{
    /**
     * @Assert\Length(max=10)
     * @Assert\Regex(pattern="/^\d+\s?[A-Za-z]{0,10}$/", message="Numéro de voie invalide (ex. 12, 37bis).")
     */
    public ?string $numVoie = null;

    /** @Assert\Length(max=20) */
    public ?string $typeVoie = null;

    /** @Assert\Length(max=100) */
    public ?string $nomVoie = null;

    /** @Assert\Regex(pattern="/^\d{5}$/", message="Le code postal doit comporter 5 chiffres.") */
    public ?string $codePostal = null;

    /**
     * @Assert\NotBlank(message="La commune est obligatoire.", normalizer="trim")
     * @Assert\Length(max=100)
     */
    public string $commune = '';
}
