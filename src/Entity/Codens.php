<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Dernier numero de groupe de doublons attribue par departement (voir CodensGenerator). */
#[ORM\Entity]
#[ORM\Table(name: 'codens', schema: 'ogmarche')]
class Codens
{
    #[ORM\Id]
    #[ORM\Column(name: 'code_dep', length: 3)]
    private string $codeDep;

    #[ORM\Column(name: 'valeur_max')]
    private int $valeurMax = 0;

    public function __construct(string $codeDep)
    {
        $this->codeDep = $codeDep;
    }

    public function getCodeDep(): string
    {
        return $this->codeDep;
    }

    public function getValeurMax(): int
    {
        return $this->valeurMax;
    }
}
