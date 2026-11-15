<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute denomination_normalise (affichee dans la recherche de doublons), reprise de la table brute.
 */
final class Version20261115064131 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute ogmarche.marche.denomination_normalise';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ogmarche.marche ADD denomination_normalise VARCHAR(100) DEFAULT NULL');
        // Reprise depuis la table brute, seulement si elle a deja sa cle row_id (ajoutee par
        // sql/marche.sql). Sur une installation neuve, marche est vide et sql/marche.sql
        // remplit denomination_normalise lui-meme.
        $rowIdExiste = $this->connection->fetchOne(
            "SELECT 1 FROM information_schema.columns WHERE table_schema = 'ogmarche' AND table_name = 'marche_geocode_raw' AND column_name = 'row_id'"
        );
        if ($rowIdExiste) {
            $this->addSql('UPDATE ogmarche.marche m SET denomination_normalise = r.denomination_normalise FROM ogmarche.marche_geocode_raw r WHERE r.row_id = m.raw_row_id');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ogmarche.marche DROP denomination_normalise');
    }
}
