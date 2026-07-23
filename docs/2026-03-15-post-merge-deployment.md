# Taches post-merge - Deploiement en production

**Date** : 2026-03-15
**Branches mergees** : `feat/notification`, `feat/match-report` -> `dev`

---

## 1. Generer les cles VAPID

Les notifications push necessitent une paire de cles VAPID. A executer **une seule fois** sur le serveur :

```bash
docker-compose exec api php bin/console app:generate-vapid-keys
```

La commande affiche les cles publique et privee. Les copier dans le fichier `.env` du serveur :

```env
VAPID_PUBLIC_KEY=<cle_publique_generee>
VAPID_PRIVATE_KEY=<cle_privee_generee>
VAPID_SUBJECT=mailto:admin@sbl-league.fr
```

> **Important** : ne jamais committer les cles privees. Les stocker uniquement dans `.env` sur le serveur ou via les variables d'environnement Docker.

---

## 2. Executer les migrations de base de donnees

Deux nouvelles migrations a appliquer :

| Migration | Description |
|-----------|-------------|
| `Version20260310100000` | Table `push_subscription`, colonnes `game.reminder_sent_at` et `division.is_finalized` |
| `Version20260315100000` | Table `match_report`, insertion du `GameStatus` "reported" |

```bash
docker-compose exec api php bin/console doctrine:migrations:migrate --no-interaction
```

Verifier le resultat :

```bash
docker-compose exec api php bin/console doctrine:migrations:status
```

---

## 3. Rebuild et redemarrage des conteneurs

Le Dockerfile a ete modifie (ajout de `gmp-dev` pour la crypto VAPID) et un nouveau service `scheduler` est present dans `docker-compose.yml`.

```bash
# Rebuild l'image API (scheduler reutilise la meme image)
docker-compose build api

# Redemarrer tous les services
docker-compose up -d
```

Verifier que le scheduler demarre :

```bash
docker-compose logs -f scheduler
```

Sortie attendue :

```
[SBL Scheduler] Attente de PostgreSQL...
[SBL Scheduler] PostgreSQL disponible!
[SBL Scheduler] Demarrage du worker de rappels de matchs...
```

Le scheduler execute `messenger:consume scheduler_default` avec un `--time-limit=3600` (redemarrage automatique toutes les heures via `restart: unless-stopped`).

---

## 4. Verifier les variables d'environnement

S'assurer que ces variables sont definies dans l'environnement de production (`.env` ou Docker) :

### Notifications push (nouveau)

| Variable | Exemple | Description |
|----------|---------|-------------|
| `VAPID_PUBLIC_KEY` | `BEl62i...` | Cle publique VAPID (generee etape 1) |
| `VAPID_PRIVATE_KEY` | `UYxI4K...` | Cle privee VAPID (generee etape 1) |
| `VAPID_SUBJECT` | `mailto:admin@sbl-league.fr` | Contact pour le serveur push |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=0` | Transport Messenger (deja configure) |

### Existantes (verifier qu'elles sont toujours presentes)

| Variable | Description |
|----------|-------------|
| `MAILER_DSN` | DSN pour les alertes email |
| `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET` | OAuth Discord |
| `DISCORD_BOT_SECRET` | Secret partage bot/API |
| `JWT_PASSPHRASE` | Passphrase des cles JWT |

---

## 5. Tests de validation post-deploiement

### 5.1 Endpoint VAPID (public)

```bash
curl https://<API_DOMAIN>/api/push/vapid-public-key
```

Reponse attendue :
```json
{"publicKey":"BEl62i..."}
```

### 5.2 Abonnement push (authentifie)

```bash
curl -X POST https://<API_DOMAIN>/api/push/subscribe \
  -H "Authorization: Bearer <JWT_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"endpoint":"https://test.push.example","keys":{"p256dh":"test","auth":"test"}}'
```

Reponse attendue : `201` ou `200`

### 5.3 Report de match (authentifie, capitaine)

```bash
curl -X POST https://<API_DOMAIN>/api/games/<GAME_ID>/report \
  -H "Authorization: Bearer <JWT_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"reason":"Indisponibilite"}'
```

Reponse attendue : `201` avec le report cree

### 5.4 Consultation des reports d'une equipe

```bash
curl "https://<API_DOMAIN>/api/teams/<TEAM_ID>/reports?season_id=<SEASON_ID>"
```

Reponse attendue :
```json
{"reports":[],"count":0,"remaining":2}
```

### 5.5 Scheduler

```bash
docker-compose logs scheduler --tail 20
```

Verifier qu'il tourne sans erreur et qu'il check les matchs toutes les heures.

---

## 6. Nouveaux endpoints API

### Notifications push

| Methode | Route | Auth | Description |
|---------|-------|------|-------------|
| `GET` | `/api/push/vapid-public-key` | Public | Cle publique VAPID pour le frontend |
| `POST` | `/api/push/subscribe` | ROLE_USER | S'abonner aux notifications |
| `DELETE` | `/api/push/subscribe` | ROLE_USER | Se desabonner |

### Report de match

| Methode | Route | Auth | Description |
|---------|-------|------|-------------|
| `POST` | `/api/games/{id}/report` | ROLE_USER (capitaine) | Reporter un match (max 2/saison/equipe) |
| `POST` | `/api/games/{id}/admin-report` | ROLE_ADMIN | Forcer un report pour les 2 equipes |
| `GET` | `/api/games/{id}/reports` | Public | Lister les reports d'un match |
| `GET` | `/api/teams/{id}/reports?season_id=X` | Public | Reports d'une equipe + compteur restant |

---

## 7. Rollback

En cas de probleme, les migrations peuvent etre annulees :

```bash
# Annuler match_report
docker-compose exec api php bin/console doctrine:migrations:execute --down DoctrineMigrations\\Version20260315100000

# Annuler push_subscription
docker-compose exec api php bin/console doctrine:migrations:execute --down DoctrineMigrations\\Version20260310100000
```

Le scheduler peut etre arrete independamment :

```bash
docker-compose stop scheduler
```
