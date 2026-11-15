<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Groupes de doublons : ogmarche.marche.codens et compteur ogmarche.codens.
 */
final class Version20261115063319 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute codens (doublons) et la table compteur codens';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ogmarche.codens (code_dep VARCHAR(3) NOT NULL, valeur_max INT NOT NULL, PRIMARY KEY (code_dep))');
        $this->addSql('ALTER TABLE ogmarche.marche ADD codens VARCHAR(30) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_marche_codens ON ogmarche.marche (codens)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ogmarche.codens');
        $this->addSql('DROP INDEX ogmarche.idx_marche_codens');
        $this->addSql('ALTER TABLE ogmarche.marche DROP codens');
    }
}
