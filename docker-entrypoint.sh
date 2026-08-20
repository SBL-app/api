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

# Les commandes ci-dessus s'exécutent en root et écrivent le cache Symfony :
# `var/cache/prod` se retrouve possédé par root, alors que les workers PHP-FPM
# tournent en www-data. Sans ce chown, la première requête échoue en écrivant
# le cache du routeur (`rename(...): Permission denied` dans RouterListener),
# et TOUTES les requêtes renvoient 500 — y compris les routes inexistantes, qui
# échouent avant même d'être résolues en 404.
#
# Le `chown` du Dockerfile ne suffit pas : il a lieu au build, avant que root
# ne recrée ces fichiers au démarrage du conteneur.
echo "[SBL] Réattribution de var/ à www-data..."
chown -R www-data:www-data var

echo "[SBL] Démarrage de PHP-FPM..."
exec php-fpm
