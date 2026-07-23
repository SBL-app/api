#!/bin/sh
set -e

echo "[SBL] Attente de PostgreSQL..."
until pg_isready -h postgres -p 5432 -q; do
    echo "[SBL] PostgreSQL non disponible, nouvelle tentative dans 2s..."
    sleep 2
done
echo "[SBL] PostgreSQL disponible!"

# Générer les clés JWT si absentes
if [ ! -f "/var/www/html/config/jwt/private.pem" ]; then
    echo "[SBL] Génération des clés JWT..."
    mkdir -p /var/www/html/config/jwt
    php bin/console lexik:jwt:generate-keypair --skip-if-exists
    echo "[SBL] Clés JWT générées."
fi

echo "[SBL] Exécution des migrations Doctrine..."
php bin/console doctrine:migrations:migrate --no-interaction
echo "[SBL] Migrations terminées."

echo "[SBL] Démarrage de PHP-FPM..."
exec php-fpm
