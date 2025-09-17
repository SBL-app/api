# Makefile pour l'API SBL
# Simplifie les tâches courantes de développement et de test

.PHONY: help install test test-unit test-integration test-functional test-coverage setup-db clean lint fix

# Affichage de l'aide par défaut
help:
	@echo "API SBL - Commandes disponibles:"
	@echo ""
	@echo "Installation et configuration:"
	@echo "  install         Installe les dépendances"
	@echo "  setup-db        Configure la base de données"
	@echo ""
	@echo "Tests:"
	@echo "  test           Exécute tous les tests"
	@echo "  test-unit      Exécute les tests unitaires"
	@echo "  test-integration  Exécute les tests d'intégration"
	@echo "  test-functional   Exécute les tests fonctionnels"
	@echo "  test-coverage  Génère un rapport de couverture"
	@echo ""
	@echo "Qualité du code:"
	@echo "  lint           Vérifie la syntaxe et le style"
	@echo "  fix            Corrige automatiquement le style"
	@echo ""
	@echo "Utilitaires:"
	@echo "  clean          Nettoie les fichiers temporaires"
	@echo "  reset          Remet à zéro l'environnement"

# Installation des dépendances
install:
	composer install --prefer-dist --no-dev --optimize-autoloader
	@echo "✅ Dépendances installées"

install-dev:
	composer install --prefer-dist --optimize-autoloader
	@echo "✅ Dépendances de développement installées"

# Configuration de la base de données
setup-db:
	php bin/console doctrine:database:create --if-not-exists
	php bin/console doctrine:migrations:migrate --no-interaction
	@echo "✅ Base de données configurée"

setup-test-db:
	php bin/console doctrine:database:create --env=test --if-not-exists
	php bin/console doctrine:schema:create --env=test
	@echo "✅ Base de données de test configurée"

# Tests
test: setup-test-db
	vendor/bin/phpunit
	@echo "✅ Tous les tests exécutés"

test-unit:
	vendor/bin/phpunit tests/Unit
	@echo "✅ Tests unitaires exécutés"

test-integration: setup-test-db
	vendor/bin/phpunit tests/Integration
	@echo "✅ Tests d'intégration exécutés"

test-functional: setup-test-db
	vendor/bin/phpunit tests/Functional
	@echo "✅ Tests fonctionnels exécutés"

test-coverage: setup-test-db
	vendor/bin/phpunit --coverage-html=var/coverage
	@echo "✅ Rapport de couverture généré dans var/coverage/"

test-watch:
	@echo "🔄 Mode surveillance des tests activé (Ctrl+C pour arrêter)"
	while true; do \
		make test-unit; \
		echo "En attente de modifications..."; \
		sleep 2; \
	done

# Qualité du code
lint:
	@echo "🔍 Vérification de la syntaxe PHP..."
	find src tests -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
	@echo "✅ Syntaxe PHP vérifiée"

# Si PHP CS Fixer est installé
fix:
	@if [ -f vendor/bin/php-cs-fixer ]; then \
		vendor/bin/php-cs-fixer fix src --rules=@Symfony; \
		vendor/bin/php-cs-fixer fix tests --rules=@Symfony; \
		echo "✅ Style de code corrigé"; \
	else \
		echo "❌ PHP CS Fixer non installé. Utilisez: composer require --dev friendsofphp/php-cs-fixer"; \
	fi

# Nettoyage
clean:
	rm -rf var/cache/* var/log/* var/test.db var/coverage/
	@echo "✅ Fichiers temporaires supprimés"

# Remise à zéro complète
reset: clean
	rm -rf vendor/ composer.lock
	composer install
	make setup-db
	@echo "✅ Environnement remis à zéro"

# Commandes de développement
dev-server:
	php -S localhost:8000 -t public/
	@echo "🚀 Serveur de développement démarré sur http://localhost:8000"

# Génération de fixtures
fixtures:
	php bin/console doctrine:fixtures:load --no-interaction
	@echo "✅ Fixtures chargées"

fixtures-test:
	php bin/console doctrine:fixtures:load --env=test --no-interaction
	@echo "✅ Fixtures de test chargées"

# Vérification de sécurité
security:
	@if [ -f vendor/bin/security-checker ]; then \
		vendor/bin/security-checker security:check; \
	else \
		composer audit; \
	fi
	@echo "✅ Vérification de sécurité terminée"

# Commandes pour CI/CD
ci-install:
	composer install --no-dev --optimize-autoloader --no-interaction

ci-test: setup-test-db
	vendor/bin/phpunit --coverage-clover=coverage.xml

# Informations sur l'environnement
info:
	@echo "📋 Informations sur l'environnement:"
	@echo "PHP version: $(shell php -v | head -n 1)"
	@echo "Composer version: $(shell composer --version)"
	@echo "Symfony version: $(shell php bin/console about | grep 'Symfony Version' | cut -d':' -f2 | xargs)"
	@echo "Environment: $(shell grep APP_ENV .env | cut -d'=' -f2)"
