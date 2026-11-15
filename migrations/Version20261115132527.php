<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute produits, specificite (a valider par l'agent) et le drapeau produits_ok, repris de la table brute.
 */
final class Version20261115132527 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute ogmarche.marche.produits, specificite et produits_ok';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ogmarche.marche ADD produits TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE ogmarche.marche ADD specificite VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE ogmarche.marche ADD produits_ok BOOLEAN DEFAULT false NOT NULL');
        // Reprise depuis la table brute, seulement si elle a deja sa cle row_id (ajoutee par
        // sql/marche.sql). Sur une installation neuve, marche est vide et sql/marche.sql
        // remplit ces colonnes lui-meme.
        $rowIdExiste = $this->connection->fetchOne(
            "SELECT 1 FROM information_schema.columns WHERE table_schema = 'ogmarche' AND table_name = 'marche_geocode_raw' AND column_name = 'row_id'"
        );
        if ($rowIdExiste) {
            $this->addSql('UPDATE ogmarche.marche m SET produits = r.produits, specificite = r.specificite FROM ogmarche.marche_geocode_raw r WHERE r.row_id = m.raw_row_id');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ogmarche.marche DROP produits');
        $this->addSql('ALTER TABLE ogmarche.marche DROP specificite');
        $this->addSql('ALTER TABLE ogmarche.marche DROP produits_ok');
    }
}
