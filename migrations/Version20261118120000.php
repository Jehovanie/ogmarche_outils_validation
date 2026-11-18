<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La specificite est desormais validee par l'agent comme les produits : drapeau specificite_ok.
 * L'import du CSV contient aussi des caracteres echappes litteralement ("MARCHÉ BIO") :
 * ils sont decodes dans les specificites deja chargees (sql/marche.sql le fait pour les nouvelles).
 */
final class Version20261118120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute ogmarche.marche.specificite_ok et decode les \\uXXXX de specificite';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ogmarche.marche ADD specificite_ok BOOLEAN DEFAULT false NOT NULL');

        $this->addSql(<<<'SQL'
            CREATE FUNCTION pg_temp.decode_unicode(t text) RETURNS text LANGUAGE plpgsql AS $$
            DECLARE m text;
            BEGIN
                IF t IS NULL THEN RETURN NULL; END IF;
                FOR m IN SELECT DISTINCT (regexp_matches(t, '\\u([0-9a-fA-F]{4})', 'g'))[1] LOOP
                    t := replace(t, '\u' || m, chr(('x' || m)::bit(16)::int));
                END LOOP;
                RETURN t;
            END $$
            SQL);
        $this->addSql("UPDATE ogmarche.marche SET specificite = pg_temp.decode_unicode(specificite) WHERE strpos(specificite, '\\u') > 0");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ogmarche.marche DROP specificite_ok');
    }
}
