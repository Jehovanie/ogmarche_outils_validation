<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute horaire_brut et les horaires qui en ont ete extraits (jours, heures, periode), a
 * verifier par l'agent, et le drapeau horaire_ok. Repris de la table brute.
 */
final class Version20261115143351 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute ogmarche.marche.horaire_brut, jours_txt, heure_debut, heure_fin, toute_annee, mois_debut, mois_fin et horaire_ok';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ogmarche.marche ADD horaire_brut VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE ogmarche.marche ADD jours_txt VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE ogmarche.marche ADD heure_debut TIME(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE ogmarche.marche ADD heure_fin TIME(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE ogmarche.marche ADD toute_annee BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE ogmarche.marche ADD mois_debut VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE ogmarche.marche ADD mois_fin VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE ogmarche.marche ADD horaire_ok BOOLEAN DEFAULT false NOT NULL');
        // Reprise depuis la table brute, seulement si elle a deja sa cle row_id (ajoutee par
        // sql/marche.sql). Sur une installation neuve, marche est vide et sql/marche.sql
        // remplit ces colonnes lui-meme.
        $rowIdExiste = $this->connection->fetchOne(
            "SELECT 1 FROM information_schema.columns WHERE table_schema = 'ogmarche' AND table_name = 'marche_geocode_raw' AND column_name = 'row_id'"
        );
        if ($rowIdExiste) {
            $this->addSql(<<<'SQL'
                UPDATE ogmarche.marche m SET
                    horaire_brut = NULLIF(btrim(r.horaire_brut), ''),
                    jours_txt = NULLIF(btrim(r.jours_txt), ''),
                    heure_debut = r.heure_debut,
                    heure_fin = r.heure_fin,
                    toute_annee = r.toute_annee,
                    mois_debut = NULLIF(btrim(r.mois_debut), ''),
                    mois_fin = NULLIF(btrim(r.mois_fin), '')
                FROM ogmarche.marche_geocode_raw r
                WHERE r.row_id = m.raw_row_id
                SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ogmarche.marche DROP horaire_brut');
        $this->addSql('ALTER TABLE ogmarche.marche DROP jours_txt');
        $this->addSql('ALTER TABLE ogmarche.marche DROP heure_debut');
        $this->addSql('ALTER TABLE ogmarche.marche DROP heure_fin');
        $this->addSql('ALTER TABLE ogmarche.marche DROP toute_annee');
        $this->addSql('ALTER TABLE ogmarche.marche DROP mois_debut');
        $this->addSql('ALTER TABLE ogmarche.marche DROP mois_fin');
        $this->addSql('ALTER TABLE ogmarche.marche DROP horaire_ok');
    }
}
