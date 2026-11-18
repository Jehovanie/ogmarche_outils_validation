# Validation des marchés

Outil interne pour valider, fiche par fiche, les données de marchés géocodées
(`data/data_geocoder_final.csv`) : nom de l'établissement, adresse, position, produits, spécificité, horaires
(un horaire propre à chaque jour), et gestion des doublons.
Un agent se voit attribuer des communes, les parcourt une à une et traite les fiches.

Symfony 8.1, Doctrine ORM, Twig, PostgreSQL.

## Prérequis

- PHP **8.4 ou plus**, avec les extensions `pdo_pgsql`, `ctype`, `iconv`, `intl`, `mbstring`
- [Composer](https://getcomposer.org/)
- PostgreSQL 16 (le client `psql` doit être disponible)
- Le fichier de données `data/data_geocoder_final.csv` (séparateur `;`, UTF-8). Il n'est pas versionné
  (`data/*.csv` est ignoré par git) : copiez-le dans `data/`.

## Installation

### 1. Récupérer le code et les dépendances

```bash
git clone git@github.com:Jehovanie/validation_marche.git
cd validation_marche
composer install
```

### 2. Configurer la base de données

Créez une base PostgreSQL et un rôle qui peut y créer des schémas et des tables :

```bash
createdb ma_base
```

L'application lit sa connexion dans des variables **en minuscules** (pas de `DATABASE_URL`).
Mettez-les dans `.env.local`, qui est ignoré par git. Ne mettez jamais de mot de passe dans `.env`, qui est versionné.

```dotenv
# .env.local
database_host=127.0.0.1
database_port=5432
database_name=ma_base
database_user=mon_utilisateur
database_password=mon_mot_de_passe
```

Pour les commandes `psql` ci-dessous, utilisez les mêmes valeurs :

```bash
export PGHOST=127.0.0.1 PGPORT=5432 PGDATABASE=ma_base PGUSER=mon_utilisateur PGPASSWORD=mon_mot_de_passe
```

### 3. Créer la table d'import et charger le CSV

```bash
php bin/console app:csv:charger
```

Cette commande crée le schéma `ogmarche` et la table `ogmarche.marche_geocode_raw` (`sql/schema.sql`) si besoin,
puis charge `data/data_geocoder_final.csv` avec la connexion de `.env.local` (pas besoin de `psql`). Elle range
chaque valeur dans la colonne qui porte le nom de l'en-tête du CSV. Le résultat attendu est `11811 ligne(s) chargée(s)`.
Pour un autre fichier : `php bin/console app:csv:charger chemin/vers/fichier.csv`. Elle refuse de charger dans une
table déjà remplie (cela doublerait les lignes).

<details>
<summary>Avec <code>psql</code> à la place</summary>

L'ordre des colonnes de la table n'est **pas** celui du CSV (`jdm_unique_id` et `prod_unique_id` sont en fin de
table) : il faut donc **toujours** passer la liste des colonnes à `\copy`, dans l'ordre de l'en-tête du CSV. Le CSV
a deux colonnes `id` : la 1re est `id`, la 2e est `id_ban`. Le chemin est relatif au dossier où vous lancez `psql`
(la racine du projet), et il n'y a pas d'option `ESCAPE` : le guillemet double (`""`) est l'échappement du CSV.

```bash
psql -v ON_ERROR_STOP=1 -f sql/schema.sql
psql -v ON_ERROR_STOP=1 -c "\\copy ogmarche.marche_geocode_raw(unique_id, jdm_unique_id, id_marche, denomination_f, departement, code_dep, jours_txt, heure_debut, heure_fin, toute_annee, mois_debut, mois_fin, produits, horaire_brut, denomination_normalise, prod_unique_id, id, clenum, specificite, jour_de_marche_1, jour_de_marche_2, jour_de_marche_3, jour_de_marche_4, jour_de_marche_5, jour_de_marche_6, jour_de_marche_7, date_data, date_inser, is_deleted, updated_at, description, action_type, longitude, latitude, "precision", id_ban, num_voie, code_postal, commune, code_insee, insee_ancien, commune_ancienne, type_voie, nom_voie, depcode) FROM 'data/data_geocoder_final.csv' WITH (FORMAT csv, DELIMITER ';', HEADER true, QUOTE '"', ENCODING 'UTF8')"
```

Le résultat attendu est `COPY 11811`. Le texte exact de cette commande est aussi dans l'en-tête de `sql/schema.sql`.

</details>

### 4. Créer les tables de l'application

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

Cela crée `public.user` (les agents) et `ogmarche.marche`, `ogmarche.marche_horaire` (créneaux par jour),
`ogmarche.commune_attribution`, `ogmarche.codens`.

### 5. Alimenter les fiches à valider

```bash
php bin/console app:marche:importer
php bin/console app:horaire:importer
```

`app:marche:importer` exécute `sql/marche.sql` avec la connexion de `.env.local` (pas besoin de `psql` ni des
variables `PG*`). Il copie les lignes non supprimées de `marche_geocode_raw` dans `ogmarche.marche` (et décode les
caractères `\uXXXX` du CSV). Il peut être relancé sans danger : il ne fait rien si `marche` contient déjà des fiches,
et refuse de tourner si la table d'import est vide (étape 3 oubliée). Équivalent avec `psql` :
`psql -v ON_ERROR_STOP=1 -f sql/marche.sql`.

`app:horaire:importer` lit ensuite le texte d'origine `horaire_brut` pour remplir les créneaux de chaque jour
(voir [Commandes](#commandes)).

### 6. Créer le premier administrateur

```bash
php bin/console app:user:create admin@md.fr 'admin' --name="Admin" --admin
php bin/console app:user:create agent1@md.fr 'agent' --name="Agent 1"
php bin/console app:user:create agent2@md.fr 'agent' --name="Agent 2"
php bin/console app:user:create agent3@md.fr 'agent' --name="Agent 3"
php bin/console app:user:create agent4@md.fr 'agent' --name="Agent 4"
php bin/console app:user:create agent5@md.fr 'agent' --name="Agent 5"
php bin/console app:user:create agent6@md.fr 'agent' --name="Agent 6"
```

L'administrateur crée ensuite les agents et leur distribue les départements depuis l'écran **Administration**
(`/admin`). En ligne de commande, `app:attribution:assign` fait la même attribution qu'à l'écran
(voir [Commandes](#commandes)).

### 7. Lancer l'application

```bash
symfony serve -d          # ou : php -S localhost:8000 -t public
```

Ouvrez `http://localhost:8000` et connectez-vous avec l'agent créé.

## Commandes

Toutes se lancent avec `php bin/console`.

| Commande | Rôle |
| --- | --- |
| `app:user:create <email> <mot_de_passe> [--name=NOM] [--admin]` | crée un compte. Avec `--admin`, le compte a l'écran Administration. Sert surtout au premier administrateur : les agents suivants se créent dans `/admin`. |
| `app:attribution:assign <email> <département>` | donne un département entier à un agent (par exemple `app:attribution:assign agent1@md.fr 01`) et le retire à l'agent précédent. Même effet que la répartition automatique, pour un seul département. |
| `app:csv:charger [fichier]` | charge le CSV (par défaut `data/data_geocoder_final.csv`) dans `ogmarche.marche_geocode_raw`, en rangeant chaque valeur selon l'en-tête du CSV (étape 3). Crée la table d'import si besoin ; refuse de charger dans une table déjà remplie. |
| `app:marche:importer` | alimente `ogmarche.marche` depuis la table d'import en exécutant `sql/marche.sql` (étape 5). Ne fait rien si `marche` contient déjà des fiches ; échoue avec un message clair si les tables sont absentes ou si le CSV n'a pas été chargé. |
| `app:horaire:importer [--force]` | remplit les créneaux par jour (`ogmarche.marche_horaire`) en lisant `horaire_brut`. Sans option, ne traite que les fiches qui n'ont encore aucun créneau. Avec `--force`, relit aussi celles qui en ont déjà, **sauf** celles dont les horaires sont déjà vérifiés par un agent. Les textes qu'elle ne reconnaît pas (marchés ponctuels « uniquement le 06/12/2026… », par exemple) sont listés et laissés à l'agent : rien n'est deviné. |
| `doctrine:migrations:migrate --no-interaction` | applique les migrations (étape 4). |

## Utilisation

Départements → communes → une fiche à la fois. Pour chaque fiche, l'agent valide le **nom**, l'**adresse** et,
si besoin, corrige la **position**. Il valide aussi les **produits**, la **spécificité** (texte libre, par exemple
« Marché bio », qu'il peut corriger ou ajouter) et les **horaires** : un tableau du lundi au dimanche, avec pour
chaque jour un ou plusieurs créneaux (heures de début et de fin, ou moment de la journée : matin, après-midi, soir,
journée). La période (toute l'année, ou de tel mois à tel mois) est commune à tous les jours. Des alertes signalent
un jour sans heure ou une fin avant le début, sans bloquer. Les fiches qui désignent le même marché se lient en groupe
de doublons (recherche par code postal, par commune ou par dénomination) ; le groupe s'affiche et se traite d'un seul
bloc. « Traiter » exige que le nom et l'adresse de chaque fiche soient validés, ainsi que les produits, la spécificité
et les horaires quand ils ne sont pas vides, et une décision garder / ne pas garder pour les existences à valider.

Un agent ne voit et ne modifie que les communes qui lui sont attribuées.

## Administration

Un administrateur est un agent qui a en plus l'écran **Administration** (`/admin`) :

- **Départements** : le tableau **Répartition actuelle** donne, pour chaque agent, son nombre de départements,
  son nombre de fiches à valider, sa progression et les fiches restantes. Tant qu'une commune n'a pas d'agent, la
  **répartition automatique** est proposée : cocher des départements et des agents, voir l'aperçu, puis l'appliquer ;
  elle équilibre le nombre de lignes (fiches) de chaque agent. Quand tout est réparti, ce bloc est masqué (pour
  changer une attribution, utiliser `app:attribution:assign`). Un département appartient à un seul agent : le donner
  à un autre agent le retire au précédent.
- **Agents** : lister les comptes, en créer un (email, nom, mot de passe d'au moins 8 caractères), le modifier, ou
  le passer administrateur. Un administrateur ne peut pas se retirer lui-même ses droits.

Un administrateur peut s'attribuer des départements pour valider des fiches lui-même.

## Tests

```bash
php bin/phpunit
```

Les tests s'exécutent sur la base configurée dans `.env.local`, dans des transactions annulées après chaque test
(`dama/doctrine-test-bundle`) : ils ne laissent rien en base. Utilisez de préférence une base de développement.

## Organisation

| Chemin | Rôle |
| --- | --- |
| `sql/schema.sql` | table d'import brut `ogmarche.marche_geocode_raw` |
| `sql/marche.sql` | alimentation de `ogmarche.marche` depuis l'import |
| `migrations/` | tables de l'application (Doctrine) |
| `src/Entity`, `src/Repository` | modèle (`Marche`, `MarcheHoraire`, `User`, `CommuneAttribution`, `Codens`) |
| `src/Service` | logique de validation, périmètre par commune, groupes de doublons, lecture des horaires (`HoraireBrut`) |
| `src/Command` | commandes console (voir [Commandes](#commandes)) |
| `src/Controller` | pages (`ValidationController`), API JSON (`AdresseApiController`), connexion |
| `src/Controller/Admin`, `src/Form` | administration : agents et distribution des départements |
| `templates/`, `public/js/validation.js`, `public/css/app.css` | interface |
| `tests/` | tests fonctionnels |

Les tables indépendantes des marchés (les comptes, `public.user`) sont dans le schéma `public` ; tout ce qui
concerne les marchés, y compris l'attribution des communes aux agents, est dans `ogmarche`. Un autre outil de
validation (par exemple les fermes) aura son propre schéma et partagera les mêmes comptes.

## Dépannage

- **`role ... does not exist` ou `permission denied`** : le rôle de `.env.local` doit exister et pouvoir créer
  un schéma dans la base.
- **`invalid input syntax` pendant le `\copy`** : le CSV n'a pas le séparateur `;` ou l'ordre de colonnes attendu,
  ou la table d'import existe déjà avec une autre définition (supprimez-la puis relancez `sql/schema.sql`).
- **Page vide après connexion** : l'agent n'a aucune commune attribuée (étape 6).
