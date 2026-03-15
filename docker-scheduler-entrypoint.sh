#!/bin/sh
set -e

echo "[SBL Scheduler] Attente de PostgreSQL..."
until pg_isready -h postgres -p 5432 -q; do
    echo "[SBL Scheduler] PostgreSQL non disponible, nouvelle tentative dans 2s..."
    sleep 2
done
echo "[SBL Scheduler] PostgreSQL disponible!"

echo "[SBL Scheduler] Démarrage du worker de rappels de matchs..."
exec php bin/console messenger:consume scheduler_default --time-limit=3600
