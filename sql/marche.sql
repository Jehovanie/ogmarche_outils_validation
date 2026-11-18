-- Alimente ogmarche.marche (table de travail de la validation) depuis
-- ogmarche.marche_geocode_raw (import brut du CSV). A lancer APRES la migration Doctrine
-- (php bin/console doctrine:migrations:migrate). Rejouable : ne fait rien si marche est deja remplie.
--
-- commune vide (ligne non geocodee) -> '' car ogmarche.marche.commune est NOT NULL.
-- Les creneaux par jour (ogmarche.marche_horaire) viennent ensuite de horaire_brut :
--   php bin/console app:horaire:importer
-- Perimetre repris : lignes non supprimees (is_deleted NULL ou 0). Les lignes sans code INSEE
-- sont chargees mais n'apparaissent dans aucune commune (donc dans aucun perimetre d'agent).

-- Cle technique sur la table brute, pour garder le lien marche.raw_row_id -> ligne d'origine.
ALTER TABLE ogmarche.marche_geocode_raw
    ADD COLUMN IF NOT EXISTS row_id bigint GENERATED ALWAYS AS IDENTITY;
CREATE UNIQUE INDEX IF NOT EXISTS uniq_marche_geocode_raw_row_id ON ogmarche.marche_geocode_raw (row_id);

-- Le CSV contient des caracteres echappes litteralement ("MARCHÉ BIO") : on les decode.
CREATE FUNCTION pg_temp.decode_unicode(t text) RETURNS text LANGUAGE plpgsql AS $$
DECLARE m text;
BEGIN
    IF t IS NULL THEN RETURN NULL; END IF;
    FOR m IN SELECT DISTINCT (regexp_matches(t, '\\u([0-9a-fA-F]{4})', 'g'))[1] LOOP
        t := replace(t, '\u' || m, chr(('x' || m)::bit(16)::int));
    END LOOP;
    RETURN t;
END $$;

INSERT INTO ogmarche.marche (
    raw_row_id, unique_id, id_marche, denomination_f, denomination_normalise, code_dep,
    num_voie, type_voie, nom_voie, code_postal, commune, code_insee,
    longitude, latitude, precision, id_ban, produits, specificite, action_type,
    horaire_brut, toute_annee, mois_debut, mois_fin
)
SELECT
    r.row_id, r.unique_id, r.id_marche, r.denomination_f, r.denomination_normalise, r.code_dep,
    r.num_voie, r.type_voie, r.nom_voie, r.code_postal, COALESCE(r.commune, ''), r.code_insee,
    r.longitude, r.latitude, r.precision, r.id_ban, r.produits, pg_temp.decode_unicode(r.specificite), r.action_type,
    NULLIF(btrim(r.horaire_brut), ''), r.toute_annee,
    NULLIF(btrim(r.mois_debut), ''), NULLIF(btrim(r.mois_fin), '')
FROM ogmarche.marche_geocode_raw r
WHERE (r.is_deleted IS NULL OR r.is_deleted = 0)
  AND NOT EXISTS (SELECT 1 FROM ogmarche.marche)
ORDER BY r.row_id;
