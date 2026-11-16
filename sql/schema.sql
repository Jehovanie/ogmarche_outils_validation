-- Schéma PostgreSQL pour data/data_geocoder_final.csv
-- Le CSV est un fichier à valider : séparateur ';', encodage UTF-8, en-tête présent,
-- 45 colonnes dont "id" en double (colonnes 17 et 36).
--   * 1re "id"  -> id          (identifiant du marché, source jours de marché)
--   * 2e  "id"  -> id_ban      (identifiant de l'adresse géocodée)
--
-- ATTENTION : l'ordre des colonnes de la table n'est PAS celui du CSV (jdm_unique_id et
-- prod_unique_id sont en fin de table). Il faut donc TOUJOURS passer la liste des colonnes
-- au \copy, dans l'ordre de l'en-tête du CSV (voir ci-dessous). Les colonnes sont permissives
-- (NULL autorisé, pas de FK ni UNIQUE) : la table reçoit les données brutes, la validation
-- se fait ensuite. Les lignes non géocodées (~700) ont commune / code_insee vides.
--
-- Import (depuis la racine du projet ; row_id est ajouté par sql/marche.sql) :
--   \copy ogmarche.marche_geocode_raw(unique_id, jdm_unique_id, id_marche, denomination_f, departement, code_dep, jours_txt, heure_debut, heure_fin, toute_annee, mois_debut, mois_fin, produits, horaire_brut, denomination_normalise, prod_unique_id, id, clenum, specificite, jour_de_marche_1, jour_de_marche_2, jour_de_marche_3, jour_de_marche_4, jour_de_marche_5, jour_de_marche_6, jour_de_marche_7, date_data, date_inser, is_deleted, updated_at, description, action_type, longitude, latitude, "precision", id_ban, num_voie, code_postal, commune, code_insee, insee_ancien, commune_ancienne, type_voie, nom_voie, depcode) FROM 'data/data_geocoder_final.csv' WITH (FORMAT csv, DELIMITER ';', HEADER true, QUOTE '"', ENCODING 'UTF8')

CREATE SCHEMA IF NOT EXISTS ogmarche;

CREATE TABLE IF NOT EXISTS ogmarche.marche_geocode_raw (
    unique_id              varchar(40),            --  1 ID-<uuid>
    id_marche              integer,                --  2
    denomination_f         varchar(100),           --  3
    departement            varchar(50),            --  4
    code_dep               varchar(3) NOT NULL,    --  5 '01'..'95', '2A', '2B', '971'...
    jours_txt              varchar(100),           --  6
    heure_debut            time,                   --  7
    heure_fin              time,                   --  8
    toute_annee            boolean,                --  9
    mois_debut             varchar(10),            -- 10
    mois_fin               varchar(10),            -- 11
    produits               text,                   -- 12 texte libre, multi-lignes, > 1600 car.
    horaire_brut           varchar(255),           -- 13
    denomination_normalise varchar(100),           -- 14
    id                     integer,                -- 15 identifiant du marché
    clenum                 varchar(20),            -- 16
    specificite            varchar(255),           -- 17
    jour_de_marche_1       varchar(100),           -- 18
    jour_de_marche_2       varchar(100),           -- 19
    jour_de_marche_3       varchar(100),           -- 20
    jour_de_marche_4       varchar(100),           -- 21
    jour_de_marche_5       varchar(100),           -- 22
    jour_de_marche_6       varchar(100),           -- 23
    jour_de_marche_7       varchar(100),           -- 24
    date_data              varchar(4),             -- 25 année, ex. '2023'
    date_inser             varchar(19),            -- 26 mixte : '2024_01' ou 'YYYY-MM-DD HH:MI:SS'
    is_deleted             smallint,               -- 27 observé : 0, 1, 3
    updated_at             varchar(26),            -- 28 'YYYY-MM-DD HH:MI:SS[.ffffff]' ou 'vide' (4 lignes)
    description            text,                   -- 29 vide dans le fichier actuel
    action_type            varchar(30) NOT NULL,   -- 30 SPECIFICATION_AVALIDER | EXISTANCE_AVALIDER
    prod_unique_id         varchar(41),            -- 31 PROD-<uuid>
    jdm_unique_id          varchar(40),            -- 32 JDM-<uuid>
    longitude              double precision,       -- 33
    latitude               double precision,       -- 34
    precision              varchar(20),            -- 35 housenumber | street | locality | municipality
    id_ban                 varchar(20),            -- 36 2e colonne "id" du CSV
    num_voie               varchar(10),            -- 37 ex. '37bis'
    code_postal            varchar(5),             -- 38
    commune                varchar(100),            -- 39
    code_insee             varchar(5),             -- 40
    insee_ancien           varchar(5),             -- 41
    commune_ancienne       varchar(100),           -- 42
    type_voie              varchar(20),            -- 43
    nom_voie               varchar(100),           -- 44
    depcode                varchar(3),             -- 45

    CONSTRAINT chk_marche_latitude   CHECK (latitude  IS NULL OR latitude  BETWEEN -90  AND 90),
    CONSTRAINT chk_marche_longitude  CHECK (longitude IS NULL OR longitude BETWEEN -180 AND 180)
);

CREATE INDEX IF NOT EXISTS idx_marche_geocode_raw_unique_id  ON ogmarche.marche_geocode_raw (unique_id);
CREATE INDEX IF NOT EXISTS idx_marche_geocode_raw_id_marche  ON ogmarche.marche_geocode_raw (id_marche);
CREATE INDEX IF NOT EXISTS idx_marche_geocode_raw_code_dep   ON ogmarche.marche_geocode_raw (code_dep);
CREATE INDEX IF NOT EXISTS idx_marche_geocode_raw_code_insee ON ogmarche.marche_geocode_raw (code_insee);
