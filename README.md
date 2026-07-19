# SBL — API

API REST de la **Splatoon Baguette League**, construite avec **Symfony 7.3**
(PHP 8.2+) et **Doctrine ORM** (PostgreSQL). Elle expose saisons, divisions,
équipes, joueurs, matchs et statistiques.

## Prérequis

- PHP 8.2+ avec les extensions `ctype`, `iconv`, `pdo_pgsql`
- Composer 2
- PostgreSQL 16 (ou via la stack Docker du dépôt `infrastructure`)

## Installation

```bash
composer install
cp .env .env.local        # puis renseignez APP_SECRET et DATABASE_URL en local
php bin/console doctrine:migrations:migrate
```

## Lancement (développement)

```bash
php -S localhost:8000 -t public/
# ou avec Symfony CLI :
symfony serve
```

## Qualité, tests et sécurité

```bash
composer test        # tests unitaires (PHPUnit)
composer phpstan     # analyse statique (PHPStan)
composer cs-check    # style de code (PHP-CS-Fixer, dry-run)
composer cs-fix      # correction automatique du style
```

- **Tests** : les entités et le `SecurityHeadersSubscriber` sont couverts par
  des tests unitaires (`tests/`). Couverture générée en CI.
- **CI** (`.github/workflows/ci.yml`) : `composer validate`, PHPUnit + couverture,
  PHPStan, PHP-CS-Fixer et build de l'image Docker à chaque push / PR.
- **CD** (`.github/workflows/cd.yml`) : déploiement SSH sur `main` (rebuild du
  service `api` + migrations Doctrine). Secrets : `SSH_HOST`, `SSH_USER`,
  `SSH_KEY`, `SSH_PORT` (optionnel), `DEPLOY_PATH`.
- **Sécurité** : voir [`SECURITY.md`](SECURITY.md) — revue OWASP Top 10.

## Docker

Le [`Dockerfile`](Dockerfile) produit une image **PHP-FPM** de production
(multi-stage, autoloader optimisé, non-root). Elle est orchestrée avec Nginx et
PostgreSQL via le dépôt `infrastructure`.

## Structure

```
src/
├── Controller/       # endpoints REST (lecture seule)
├── Entity/           # entités Doctrine
├── Repository/       # repositories Doctrine
├── DataFixtures/     # jeux de données de démonstration
└── EventSubscriber/  # SecurityHeadersSubscriber (en-têtes de sécurité)
tests/                # tests unitaires (PHPUnit)
```
