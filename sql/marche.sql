-- Alimente ogmarche.marche (table de travail de la validation) depuis
-- ogmarche.marche_geocode_raw (import brut du CSV). A lancer APRES la migration Doctrine
-- (php bin/console doctrine:migrations:migrate). Rejouable : ne fait rien si marche est deja remplie.
--
-- Perimetre repris : lignes non supprimees (is_deleted NULL ou 0). Les lignes sans code INSEE
-- sont chargees mais n'apparaissent dans aucune commune (donc dans aucun perimetre d'agent).

-- Cle technique sur la table brute, pour garder le lien marche.raw_row_id -> ligne d'origine.
ALTER TABLE ogmarche.marche_geocode_raw
    ADD COLUMN IF NOT EXISTS row_id bigint GENERATED ALWAYS AS IDENTITY;
CREATE UNIQUE INDEX IF NOT EXISTS uniq_marche_geocode_raw_row_id ON ogmarche.marche_geocode_raw (row_id);

INSERT INTO ogmarche.marche (
    raw_row_id, unique_id, id_marche, denomination_f, code_dep,
    num_voie, type_voie, nom_voie, code_postal, commune, code_insee,
    longitude, latitude, precision, id_ban
)
SELECT
    r.row_id, r.unique_id, r.id_marche, r.denomination_f, r.code_dep,
    r.num_voie, r.type_voie, r.nom_voie, r.code_postal, r.commune, r.code_insee,
    r.longitude, r.latitude, r.precision, r.id_ban
FROM ogmarche.marche_geocode_raw r
WHERE (r.is_deleted IS NULL OR r.is_deleted = 0)
  AND NOT EXISTS (SELECT 1 FROM ogmarche.marche)
ORDER BY r.row_id;
