<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Fiches a lier a la fiche courante (corps JSON de POST /api/marche/{id}/lier). */
final readonly class LierDto
{
    /** @param list<int> $ids */
    public function __construct(
        #[Assert\Count(min: 1, max: 50, minMessage: 'Sélectionnez au moins une fiche à lier.')]
        #[Assert\All([new Assert\Type('int'), new Assert\Positive()])]
        public array $ids = [],
    ) {
    }
}
