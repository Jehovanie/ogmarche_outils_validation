# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

General Symfony conventions (attributes, autowiring, Flex, `#[MapRequestPayload]`, migrations, etc.) are in `AGENTS.md`; follow them. This file covers what is specific to this project. Code, comments, UI and the README are in French; keep it that way.

## What this is

Internal tool for agents to validate geocoded market records ("fiches de marché") one at a time: name, address, position, and duplicate grouping. Symfony 8.1 / PHP ≥ 8.4, Doctrine ORM, Twig, PostgreSQL 16. Full setup steps are in `README.md`.

## Commands

```bash
composer install
symfony serve -d                                   # or: php -S localhost:8000 -t public
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:user:create <email> <password> [--name=...] [--admin]   # first admin; agents are then created in /admin
php bin/console app:attribution:assign <email> <dep>   # gives the whole département to the agent (same as /admin)

php bin/phpunit                                    # all tests
php bin/phpunit --filter testAgentCorrigeValideEtTraiteUneFiche   # single test
php bin/phpunit tests/Service/CodePostalDepartementTest.php       # single file
```

PHPUnit is configured with `failOnDeprecation`, `failOnNotice` and `failOnWarning`: a deprecation in `src/` fails the suite.

## Database

- The connection comes from **lowercase env vars** (`database_host`, `database_port`, `database_name`, `database_user`, `database_password`), not `DATABASE_URL`. Override them in `.env.local`.
- **Schema rule**: tables independent of markets go in `public` (accounts: `public.user`); anything tied to markets goes in `ogmarche`, including `commune_attribution`. A future validation tool for farms will get its own schema (e.g. `ogferme`) with its own attribution table and share `public.user`. Put new tables in the right schema.
- Data pipeline, partly outside Doctrine:
  1. `sql/schema.sql` creates `ogmarche.marche_geocode_raw`, which is loaded from `data/data_geocoder_final.csv` (git-ignored) with `\copy`.
  2. Doctrine migrations create `user`, `ogmarche.marche`, `ogmarche.commune_attribution` and `ogmarche.codens`.
  3. `sql/marche.sql` copies raw rows into `ogmarche.marche` (it does nothing if `marche` already has rows).
- `doctrine.dbal.schema_filter` in `config/packages/doctrine.yaml` restricts Doctrine to its own tables, so `marche_geocode_raw` stays out of migration diffs. If you add an entity, **extend that regex** or `make:migration` won't see the new table. `user` is a PostgreSQL reserved word and reaches the filter as `"user"`, hence the optional quotes.
- There is no separate test database. Tests run against the configured database, inside transactions rolled back by `dama/doctrine-test-bundle`. Test fixtures use département `99` / INSEE `99001`.

## Architecture

**Agent scope is the core rule.** `CommuneAttribution` (`code_dep`, `code_insee`, `user_id`) defines which communes an agent may see and edit. `user_id` points across schemas, so there is no ORM relation. Every read and write goes through `AdresseValidationService`, which calls `assertDansPerimetre` / `assertCommuneDansPerimetre` before touching a `Marche`. Repository queries that list records join `CommuneAttribution` on `(codeDep, codeInsee)` and filter by `userId`. Any new action must keep this check.

**Administration** (`/admin`, `ROLE_ADMIN`; `ROLE_ADMIN` inherits `ROLE_USER`, so an admin can also validate). `src/Controller/Admin` + `src/Form`. A département belongs to one agent: giving it to an agent deletes every attribution of the département and gives all its communes (those that have fiches) to that agent. The only UI for this is the automatic distribution on `/admin/departements`: `Repartition::planifier()` (pure, deterministic, so the preview equals what is applied) assigns whole départements largest-first to the least-loaded agent by fiche count, and `AttributionService::appliquerRepartition()` writes it in one transaction. `app:attribution:assign` gives a single département. Account creation and password hashing go through `AgentService`, also used by `app:user:create`.

**Request flow.**
- `ValidationController` renders the HTML pages: `/depChoice` → `/dep/{dep}` → `/validation/{dep}/{insee}`. The last page shows the next untreated, non-`rappel` record (lowest id first), or the one given by `?fiche=<id>`.
- `AdresseApiController` exposes the XHR endpoints under `/api/marche/{id}/…` (nom, nom-ok, adresse, adresse-ok, position, traite, lier, delier, candidats).
  - Each POST checks the CSRF token `marche` from the `X-CSRF-Token` header.
  - JSON bodies are bound to readonly DTOs in `src/Dto` through `#[MapRequestPayload]`; validation failures return 422 with `violations`.
  - Business-rule failures are thrown as `\DomainException` and mapped to 422 `{ok:false, message}`.
- `public/js/validation.js` is plain JS (jQuery/DataTables are loaded from CDN on the fiche page). It reads API URLs and the CSRF token from `data-*` attributes on `#fiche` and on each card in `templates/validation/_fiche_card.html.twig`, which are generated with `path()`. Add new endpoints the same way; don't hardcode URLs.

**Validation state on `Marche`.**
- `nomOk` and `adresseOk` are set explicitly by the agent. Editing the name or the address (`changeDenomination` / `changeAdresse`) resets the matching flag to false.
- `marquerTraite` requires both flags on **every record of the group**, and stamps `validatedBy` and `dateDeValidation` using the injected `ClockInterface`.
- A postal code / département mismatch (`CodePostalDepartement`) is only a warning, never a blocker.

**Duplicate groups ("codens").**
- Linked records share `Marche::codens = "id_<dep>_<n>"`.
- `CodensGenerator` allocates `n` with an atomic `INSERT … ON CONFLICT … RETURNING` on `ogmarche.codens`, which is safe under concurrency. Don't replace it with read-then-write.
- `lier` merges existing groups into the oldest codens, inside a transaction, and records must belong to the same département.
- `delier` dissolves a group when only one record would remain.
- The fiche page displays the whole group, and actions on the group apply as a block.
