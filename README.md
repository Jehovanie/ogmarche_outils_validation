# Validation des marchés

Outil interne pour valider, fiche par fiche, les données de marchés géocodées
(`data/data_geocoder_final.csv`) : nom de l'établissement, adresse, position, et gestion des doublons.
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
psql -v ON_ERROR_STOP=1 -f sql/schema.sql
psql -v ON_ERROR_STOP=1 -c "\copy ogmarche.marche_geocode_raw FROM 'data/data_geocoder_final.csv' WITH (FORMAT csv, DELIMITER ';', HEADER true, ENCODING 'UTF8')"
```

Cela crée le schéma `ogmarche` et la table `ogmarche.marche_geocode_raw`, qui reçoit les données brutes.
Les colonnes de la table sont dans le même ordre que le CSV : n'ajoutez pas de liste de colonnes à `\copy`.
Le résultat attendu est `COPY 11811`.

### 4. Créer les tables de l'application

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

Cela crée `public.user` (les agents) et `ogmarche.marche`, `ogmarche.commune_attribution`, `ogmarche.codens`.

### 5. Alimenter les fiches à valider

```bash
psql -v ON_ERROR_STOP=1 -f sql/marche.sql
```

Ce script copie les lignes non supprimées de `marche_geocode_raw` dans `ogmarche.marche`.
Il peut être relancé sans danger : il ne fait rien si `marche` contient déjà des fiches.

### 6. Créer un agent et lui attribuer des communes

```bash
php bin/console app:user:create agent@md.fr 'agent' --name="Agent"
php bin/console app:attribution:assign agent@md.fr 01
```

La seconde commande attribue à l'agent toutes les communes du département `01` qui n'ont pas encore d'agent.
Répétez-la pour chaque département. Ajoutez `--admin` à la première pour créer un administrateur.

### 7. Lancer l'application

```bash
symfony serve -d          # ou : php -S localhost:8000 -t public
```

Ouvrez `http://localhost:8000` et connectez-vous avec l'agent créé.

## Utilisation

Départements → communes → une fiche à la fois. Pour chaque fiche, l'agent valide le **nom**, l'**adresse** et,
si besoin, corrige la **position**. Les fiches qui désignent le même marché se lient en groupe de doublons
(recherche par code postal, par commune ou par dénomination) ; le groupe s'affiche et se traite d'un seul bloc. « Traiter » exige que
le nom et l'adresse de chaque fiche soient validés.

Un agent ne voit et ne modifie que les communes qui lui sont attribuées. Il n'y a pas de bouton de déconnexion à
l'écran : l'adresse est `/logout`.

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
| `src/Entity`, `src/Repository` | modèle (`Marche`, `User`, `CommuneAttribution`, `Codens`) |
| `src/Service` | logique de validation, périmètre par commune, génération des groupes de doublons |
| `src/Controller` | pages (`ValidationController`), API JSON (`AdresseApiController`), connexion |
| `templates/`, `public/js/validation.js`, `public/css/app.css` | interface |
| `tests/` | tests fonctionnels |

Les tables des utilisateurs sont dans le schéma `public` ; tout ce qui concerne les marchés est dans `ogmarche`.

## Dépannage

- **`role ... does not exist` ou `permission denied`** : le rôle de `.env.local` doit exister et pouvoir créer
  un schéma dans la base.
- **`invalid input syntax` pendant le `\copy`** : le CSV n'a pas le séparateur `;` ou l'ordre de colonnes attendu,
  ou la table d'import existe déjà avec une autre définition (supprimez-la puis relancez `sql/schema.sql`).
- **Page vide après connexion** : l'agent n'a aucune commune attribuée (étape 6).
