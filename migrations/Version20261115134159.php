<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute action_type (SPECIFICATION_AVALIDER ou EXISTANCE_AVALIDER), repris de la table brute :
 * il decide de ce que l'agent doit valider avant de traiter la fiche.
 */
final class Version20261115134159 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute ogmarche.marche.action_type';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ogmarche.marche ADD action_type VARCHAR(30) DEFAULT \'EXISTANCE_AVALIDER\' NOT NULL');
        // Reprise depuis la table brute, seulement si elle a deja sa cle row_id (ajoutee par
        // sql/marche.sql). Sur une installation neuve, marche est vide et sql/marche.sql
        // remplit action_type lui-meme.
        $rowIdExiste = $this->connection->fetchOne(
            "SELECT 1 FROM information_schema.columns WHERE table_schema = 'ogmarche' AND table_name = 'marche_geocode_raw' AND column_name = 'row_id'"
        );
        if ($rowIdExiste) {
            $this->addSql('UPDATE ogmarche.marche m SET action_type = r.action_type FROM ogmarche.marche_geocode_raw r WHERE r.row_id = m.raw_row_id');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ogmarche.marche DROP action_type');
    }
}
