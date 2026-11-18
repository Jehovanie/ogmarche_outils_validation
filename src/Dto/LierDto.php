<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Fiches a lier a la fiche courante (corps JSON de POST /api/marche/{id}/lier). */
final class LierDto
{
    /**
     * @var list<int>
     *
     * @Assert\Count(min=1, max=50, minMessage="Sélectionnez au moins une fiche à lier.")
     * @Assert\All({@Assert\Type("int"), @Assert\Positive})
     */
    public array $ids = [];
}
