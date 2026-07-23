# SBL API

API REST pour la gestion de la ligue sportive SBL (Symfony Baguette League). Construite avec Symfony 7.3, PHP 8.3 et PostgreSQL.

## Prérequis

- PHP >= 8.2
- Composer
- PostgreSQL 16+ (ou SQLite pour les tests)
- OpenSSL (pour les clés JWT)

## Installation

```bash
# Installer les dépendances (avec dépendances de dev)
make install-dev

# Configurer l'environnement
cp .env .env.local
# Éditer .env.local avec vos paramètres (base de données, Discord OAuth, JWT)

# Générer les clés JWT
php bin/console lexik:jwt:generate-keypair

# Créer la base de données et lancer les migrations
make setup-db
```

## Développement

```bash
# Lancer le serveur de développement
make dev-server    # http://localhost:8000

# Vérifier la syntaxe PHP
make lint

# Corriger le style de code
make fix
```

## Tests

```bash
make test               # Tous les tests
make test-unit          # Tests unitaires
make test-integration   # Tests d'intégration
make test-functional    # Tests fonctionnels
make test-coverage      # Rapport de couverture (var/coverage/)

# Lancer un test spécifique
php bin/phpunit tests/Functional/Controller/AuthControllerTest.php
php bin/phpunit --filter testLoginSuccess
```

## Gestion des utilisateurs (CLI)

```bash
# Créer un utilisateur avec accès API
php bin/console app:auth:manage-user create dev_user --password=secret --roles=ROLE_API --api-key

# Lister les utilisateurs
php bin/console app:auth:manage-user list

# Générer un token JWT
php bin/console app:auth:manage-user token dev_user
```

## Documentation

- [Documentation API](API_DOCUMENTATION.md) - Toutes les routes et modèles de données
