<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Nom de l'etablissement corrige par l'agent (corps JSON de POST /api/marche/{id}/nom). */
final readonly class NomDto
{
    public function __construct(
        #[Assert\NotBlank(message: "Le nom de l'établissement est obligatoire.", normalizer: 'trim')]
        #[Assert\Length(max: 100)]
        public string $denomination = '',
    ) {
    }
}
