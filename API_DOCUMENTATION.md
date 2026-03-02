# Documentation API SBL

API REST pour la gestion d'une ligue sportive. Authentification JWT requise pour les opérations de modification.

**URL de base** : `/api`

---

## Authentification

Toutes les routes sont préfixées par `/api/auth`.

### POST /api/auth/login

Connexion avec nom d'utilisateur et mot de passe. Rate limited.

```json
// Body
{ "username": "string", "password": "string" }

// Réponse 200
{
    "token": "jwt_token",
    "user": { "id": 1, "username": "string", "roles": ["ROLE_USER"], "is_active": true },
    "expires_in": 3600
}
```

### POST /api/auth/login-api-key

Connexion avec clé API. Rate limited.

```json
// Body
{ "api_key": "string" }

// Réponse 200
{
    "token": "jwt_token",
    "user": { ... },
    "login_method": "api_key",
    "expires_in": 3600
}
```

### POST /api/auth/refresh

Renouveler un token JWT (possible jusqu'à 24h après expiration).

```
Authorization: Bearer {token}
```

### POST /api/auth/verify

Vérifier la validité d'un token.

```
Authorization: Bearer {token}
```

### POST /api/auth/logout

Déconnexion et invalidation des tokens.

```
Authorization: Bearer {token}
```

### GET /api/auth/discord

Initier le flux OAuth Discord. Redirige vers Discord.

### GET /api/auth/discord/callback

Callback OAuth Discord. Crée ou met à jour l'utilisateur.

### POST /api/auth/discord/bot

Authentification Discord pour le bot. Nécessite le header `X-Bot-Secret`.

```json
// Body
{ "discord_id": "string", "username": "string" }
```

---

## Utilisateurs

### GET /api/users/me

Récupère le profil de l'utilisateur authentifié. Nécessite `ROLE_USER`.

### GET /api/users/me/teams

Récupère les équipes de l'utilisateur authentifié. Nécessite `ROLE_USER`.

---

## Équipes

### GET /api/teams

Liste toutes les équipes.

```json
// Réponse 200
[
    { "id": 1, "name": "Nom", "captain": "Nom capitaine", "captain_id": 2 }
]
```

### GET /api/teams/{id}

Récupère une équipe. Supporte `?expand=players,stats` pour inclure les détails.

```json
// Réponse 200 (avec expand)
{
    "team": { "id": 1, "name": "Nom", "captain": "Nom", "captain_id": 2 },
    "players": [ { "id": 1, "name": "Joueur", "discord": "discord#1234" } ],
    "stats": [ { "division_name": "Division A", "wins": 5, "losses": 2, "points": 16 } ],
    "players_count": 5,
    "divisions_count": 2
}
```

### POST /api/teams

Crée une équipe. Option `"captain": true` pour s'auto-assigner capitaine.

```json
{ "name": "Nom de l'équipe" }
```

### PUT /api/teams/{id}

Mise à jour complète d'une équipe.

### PATCH /api/teams/{id}

Mise à jour partielle d'une équipe.

### DELETE /api/teams/{id}

Supprime une équipe.

---

## Membres d'équipe

Nécessite `ROLE_USER`. Le capitaine peut ajouter/retirer des membres et changer les rôles.

### GET /api/teams/{teamId}/members

Liste les membres d'une équipe.

### POST /api/teams/{teamId}/members

Ajouter un membre à l'équipe (capitaine requis).

```json
{ "discord_id": "string" }
```

### DELETE /api/teams/{teamId}/members

Retirer un membre. Un membre peut se retirer lui-même, ou le capitaine peut retirer un membre.

```json
{ "discord_id": "string" }
```

### PATCH /api/teams/{teamId}/members/role

Changer le rôle d'un membre (capitaine requis).

```json
{ "discord_id": "string", "role": "captain|member" }
```

---

## Joueurs

### GET /api/players

Liste les joueurs. Filtres : `?team_id=X`

### GET /api/players/{id}

Récupère un joueur avec ses statistiques.

### POST /api/players

```json
{ "name": "Nom", "discord": "discord#1234", "team": 1 }
```

### PUT /api/players/{id}

Mise à jour complète.

### PATCH /api/players/{id}

Mise à jour partielle.

### DELETE /api/players/{id}

---

## Matchs

### GET /api/games

Liste les matchs. Filtres : `?week=X`, `?season_id=X`, `?team_id=X`, `?division_id=X`, `?scheduled=false`

```json
// Réponse 200
[
    {
        "id": 1, "date": "2024-08-28 20:00:00", "week": 1,
        "team1": "Équipe A", "team2": "Équipe B",
        "score1": 2, "score2": 1, "winner": 1,
        "status": "joué", "division": "Division A"
    }
]
```

### GET /api/games/{id}

Récupère un match.

### POST /api/games

```json
{
    "date": "2024-08-28T20:00:00", "week": 1,
    "team1": 1, "team2": 2,
    "score1": 0, "score2": 0,
    "status": 1, "division": 1
}
```

### PUT /api/games/{id}

### PATCH /api/games/{id}

### DELETE /api/games/{id}

---

## Propositions de match

### GET /api/match-proposals

Liste les propositions. Filtres : `?game_id=X`, `?receiver_id=X`, `?discord_id=X`, `?status=pending|accepted|rejected|counter`

### GET /api/match-proposals/{id}

### POST /api/match-proposals

Créer une proposition de date (nécessite d'être capitaine d'une des équipes du match).

```json
{
    "game_id": 1,
    "proposed_date": "2024-09-15T20:00:00",
    "counter_to_id": null
}
```

### PATCH /api/match-proposals/{id}

Accepter ou rejeter une proposition.

```json
{ "status": "accepted|rejected" }
```

### DELETE /api/match-proposals/{id}

---

## Saisons

### GET /api/seasons

Liste toutes les saisons avec statistiques (total matchs, matchs terminés, pourcentage).

```json
// Réponse 200
[
    {
        "id": 1, "name": "Saison 2024",
        "start_date": "2024-01-01", "end_date": "2024-12-31",
        "total_games": 48, "finished_games": 32, "percentage": 66.67
    }
]
```

### GET /api/seasons/{id}

Récupère une saison avec statistiques.

### GET /api/seasons/current

Récupère la saison en cours.

### GET /api/seasons/current/week

Récupère la semaine actuelle et la semaine maximum de la saison en cours.

### GET /api/seasons/{seasonId}/teams

Liste les équipes inscrites à une saison.

### GET /api/seasons/{id}/completion

Progression de la saison (matchs joués/total). Paramètre optionnel : `?decimal=2`

```json
{ "id": 1, "name": "Saison 2024", "total": 48, "finished": 32, "pourcent": "66.67" }
```

### POST /api/seasons

### PUT /api/seasons/{id}

### PATCH /api/seasons/{id}

### DELETE /api/seasons/{id}

---

## Divisions

### GET /api/divisions

Liste toutes les divisions.

```json
[{ "id": 1, "name": "Division A", "season_id": 1, "season_name": "Saison 2024" }]
```

### GET /api/divisions/{id}

### GET /api/seasons/{seasonId}/divisions

Liste les divisions d'une saison avec les équipes et statistiques.

### GET /api/divisions/{divisionId}/teams

Classement des équipes d'une division.

```json
[
    {
        "position": 1,
        "team": { "id": 1, "name": "Équipe A" },
        "players_count": 5,
        "wins": 8, "losses": 2, "ties": 0, "points": 24
    }
]
```

### GET /api/divisions/{divisionId}/games

Matchs d'une division groupés par semaine.

### GET /api/divisions/{divisionId}/details

Détails complets : classement + équipes + matchs.

### POST /api/divisions/{divisionId}/schedule

Générer un planning de matchs Round Robin ou Double Round Robin.

```json
{ "type": "round_robin|double_round_robin" }
```

### POST /api/divisions

### PUT /api/divisions/{id}

### PATCH /api/divisions/{id}

### DELETE /api/divisions/{id}

---

## Statistiques d'équipes

### GET /api/team-stats

Filtres : `?team_id=X`, `?division_id=X`

```json
[
    {
        "id": 1, "team_id": 1, "team_name": "Équipe A",
        "division_id": 1, "division_name": "Division A",
        "season_id": 1, "season_name": "Saison 2024",
        "wins": 8, "losses": 2, "ties": 0,
        "winRounds": 24, "looseRounds": 8, "points": 24
    }
]
```

### GET /api/team-stats/{id}

### POST /api/team-stats

```json
{ "team": 1, "division": 1, "wins": 0, "losses": 0, "ties": 0, "winRounds": 0, "looseRounds": 0, "points": 0 }
```

### PUT /api/team-stats/{id}

### PATCH /api/team-stats/{id}

### DELETE /api/team-stats/{id}

---

## Statuts de match

### GET /api/game-statuses

```json
[{ "id": 1, "name": "programmé" }, { "id": 2, "name": "joué" }]
```

### GET /api/game-statuses/{id}

### POST /api/game-statuses

### PUT /api/game-statuses/{id}

### PATCH /api/game-statuses/{id}

### DELETE /api/game-statuses/{id}

---

## Inscriptions

### GET /api/registrations

Filtres : `?season_id=X`, `?team_id=X`

```json
[{ "id": 1, "season": "Saison 2024", "team": "Équipe A" }]
```

### GET /api/registrations/{id}

### POST /api/registrations

```json
{ "season": 1, "team": 1 }
```

### PUT /api/registrations/{id}

### PATCH /api/registrations/{id}

### DELETE /api/registrations/{id}

---

## Codes de réponse

| Code | Signification |
|------|---------------|
| 200 | Requête réussie |
| 201 | Ressource créée |
| 204 | Suppression réussie |
| 400 | Paramètres invalides / JSON malformé |
| 401 | Authentification requise ou token invalide |
| 403 | Permissions insuffisantes |
| 404 | Ressource non trouvée |
| 409 | Conflit (état contradictoire) |
| 422 | Erreur de validation |
| 429 | Rate limit dépassé |

## Règles d'accès

- **GET** (lecture) : accessible publiquement sur la plupart des routes
- **POST/PUT/PATCH/DELETE** (modification) : nécessite `ROLE_API` ou `ROLE_ADMIN`
- **Routes /api/users/me** et **membres d'équipe** : nécessite `ROLE_USER`
- **Routes auth** (`/login`, `/login-api-key`, `/discord`) : accès libre
