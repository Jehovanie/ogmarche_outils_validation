<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Genere les cles de groupes de doublons "id_<dep>_<n>". Le compteur par departement est
 * incremente en une seule requete atomique (upsert + RETURNING) : deux agents du meme
 * departement ne peuvent jamais obtenir le meme numero.
 */
class CodensGenerator
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function suivant(string $codeDep): string
    {
        $n = $this->connection->fetchOne(
            'INSERT INTO ogmarche.codens (code_dep, valeur_max) VALUES (:dep, 1)
             ON CONFLICT (code_dep) DO UPDATE SET valeur_max = ogmarche.codens.valeur_max + 1
             RETURNING valeur_max',
            ['dep' => $codeDep],
        );

        return \sprintf('id_%s_%d', $codeDep, $n);
    }
}
