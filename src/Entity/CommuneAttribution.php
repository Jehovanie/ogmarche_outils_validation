<?php

namespace App\Entity;

use App\Repository\CommuneAttributionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Perimetre d'un agent : une commune (code INSEE) d'un departement lui est attribuee.
 * user_id reference public."user" (schema different, donc pas de cle etrangere ORM).
 */
#[ORM\Entity(repositoryClass: CommuneAttributionRepository::class)]
#[ORM\Table(name: 'commune_attribution', schema: 'ogmarche')]
#[ORM\UniqueConstraint(name: 'uniq_commune_attribution', columns: ['code_dep', 'code_insee'])]
#[ORM\Index(name: 'idx_commune_attribution_user', columns: ['user_id'])]
class CommuneAttribution
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'code_dep', length: 3)]
    private string $codeDep;

    #[ORM\Column(name: 'code_insee', length: 5)]
    private string $codeInsee;

    #[ORM\Column(name: 'user_id')]
    private int $userId;

    public function __construct(string $codeDep, string $codeInsee, int $userId)
    {
        $this->codeDep = $codeDep;
        $this->codeInsee = $codeInsee;
        $this->userId = $userId;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodeDep(): string
    {
        return $this->codeDep;
    }

    public function getCodeInsee(): string
    {
        return $this->codeInsee;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }
}
