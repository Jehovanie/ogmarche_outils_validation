<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Validation du nom de l'etablissement : drapeau nom_ok.
 */
final class Version20261115061310 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute ogmarche.marche.nom_ok';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ogmarche.marche ADD nom_ok BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ogmarche.marche DROP nom_ok');
    }
}
