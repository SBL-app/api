#!/bin/bash

# Script d'exécution des tests pour l'API SBL
# Usage: ./run-tests.sh [type] [options]
# Types: unit, integration, functional, all
# Options: --coverage, --filter=TestName

set -e

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Fonction d'affichage coloré
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Vérifications préalables
check_requirements() {
    print_status "Vérification des prérequis..."
    
    if [ ! -f "vendor/bin/phpunit" ]; then
        print_error "PHPUnit n'est pas installé. Exécutez 'composer install' d'abord."
        exit 1
    fi
    
    if [ ! -f ".env.test" ]; then
        print_warning "Fichier .env.test manquant. Création automatique..."
        cp .env.test.dist .env.test 2>/dev/null || true
    fi
}

# Préparation de la base de données de test
setup_test_db() {
    print_status "Préparation de la base de données de test..."
    
    # Supprimer l'ancienne base si elle existe
    rm -f var/test.db
    
    # Créer la base de données et le schéma
    php bin/console doctrine:database:create --env=test --if-not-exists --quiet
    php bin/console doctrine:schema:create --env=test --quiet
    
    print_status "Base de données de test prête."
}

# Exécution des tests
run_tests() {
    local test_type="$1"
    local options="$2"
    
    case $test_type in
        "unit")
            print_status "Exécution des tests unitaires..."
            vendor/bin/phpunit tests/Unit $options
            ;;
        "integration")
            print_status "Exécution des tests d'intégration..."
            setup_test_db
            vendor/bin/phpunit tests/Integration $options
            ;;
        "functional")
            print_status "Exécution des tests fonctionnels..."
            setup_test_db
            vendor/bin/phpunit tests/Functional $options
            ;;
        "all"|"")
            print_status "Exécution de tous les tests..."
            setup_test_db
            vendor/bin/phpunit $options
            ;;
        *)
            print_error "Type de test inconnu: $test_type"
            echo "Types disponibles: unit, integration, functional, all"
            exit 1
            ;;
    esac
}

# Affichage de l'aide
show_help() {
    echo "Script d'exécution des tests pour l'API SBL"
    echo ""
    echo "Usage: $0 [TYPE] [OPTIONS]"
    echo ""
    echo "Types de tests:"
    echo "  unit         Tests unitaires uniquement"
    echo "  integration  Tests d'intégration uniquement"
    echo "  functional   Tests fonctionnels uniquement"
    echo "  all          Tous les tests (défaut)"
    echo ""
    echo "Options:"
    echo "  --coverage             Génère un rapport de couverture"
    echo "  --filter=NomTest       Exécute seulement les tests correspondants"
    echo "  --stop-on-failure      Arrête à la première erreur"
    echo "  --verbose              Mode verbeux"
    echo "  --help                 Affiche cette aide"
    echo ""
    echo "Exemples:"
    echo "  $0                     # Tous les tests"
    echo "  $0 unit                # Tests unitaires seulement"
    echo "  $0 functional --coverage # Tests fonctionnels avec couverture"
    echo "  $0 --filter=Division   # Tests contenant 'Division'"
}

# Point d'entrée principal
main() {
    local test_type=""
    local options=""
    
    # Traitement des arguments
    for arg in "$@"; do
        case $arg in
            --help|-h)
                show_help
                exit 0
                ;;
            --coverage)
                options="$options --coverage-html=var/coverage"
                ;;
            --filter=*)
                options="$options --filter=${arg#*=}"
                ;;
            --stop-on-failure)
                options="$options --stop-on-failure"
                ;;
            --verbose|-v)
                options="$options --verbose"
                ;;
            unit|integration|functional|all)
                test_type="$arg"
                ;;
            *)
                print_error "Option inconnue: $arg"
                show_help
                exit 1
                ;;
        esac
    done
    
    check_requirements
    run_tests "$test_type" "$options"
    
    print_status "Tests terminés avec succès!"
}

# Exécution du script
main "$@"
