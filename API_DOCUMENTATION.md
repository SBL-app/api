# Documentation API SBL (Sport Business League)

## Vue d'ensemble

Cette API REST permet de gérer un système de ligue sportive avec équipes, joueurs, matchs, divisions, saisons et statistiques. L'API est construite avec Symfony et utilise JWT pour l'authentification.

**URL de base**: `/api`

## Table des matières

1. [Authentification](#authentification)
2. [Équipes (Teams)](#équipes-teams)
3. [Joueurs (Players)](#joueurs-players)
4. [Matchs (Games)](#matchs-games)
5. [Divisions](#divisions)
6. [Saisons (Seasons)](#saisons-seasons)
7. [Statistiques d'équipes (Team Stats)](#statistiques-déquipes-team-stats)
8. [Statuts de match (Game Status)](#statuts-de-match-game-status)
9. [Inscriptions (Registrations)](#inscriptions-registrations)
10. [Codes de réponse](#codes-de-réponse)
11. [Modèles de données](#modèles-de-données)

---

## Authentification

Toutes les routes sont préfixées par `/api/auth`

### POST /api/auth/login

Connexion avec nom d'utilisateur et mot de passe.

**Body (JSON):**

```json
{
    "username": "string",
    "password": "string"
}
```

**Réponse (200):**

```json
{
    "token": "jwt_token",
    "user": {
        "id": 1,
        "username": "string",
        "roles": ["ROLE_USER"],
        "is_active": true
    },
    "expires_in": 3600
}
```

### POST /api/auth/login-api-key

Connexion avec clé API.

**Body (JSON):**

```json
{
    "api_key": "string"
}
```

**Réponse (200):**

```json
{
    "token": "jwt_token",
    "user": {...},
    "login_method": "api_key",
    "expires_in": 3600
}
```

### POST /api/auth/refresh

Actualisation du token JWT.

**Headers:**

```
Authorization: Bearer {token}
```

**Réponse (200):**

```json
{
    "token": "new_jwt_token",
    "expires_in": 3600
}
```

### POST /api/auth/verify

Vérification de la validité du token.

**Headers:**

```
Authorization: Bearer {token}
```

**Réponse (200):**

```json
{
    "valid": true,
    "user": {...}
}
```

### GET /api/auth/me

Récupération du profil utilisateur.

**Headers:**

```
Authorization: Bearer {token}
```

**Réponse (200):**

```json
{
    "id": 1,
    "username": "string",
    "roles": ["ROLE_USER"],
    "is_active": true
}
```

### POST /api/auth/create-user

Création d'un nouvel utilisateur (temporaire, à sécuriser en production).

**Body (JSON):**

```json
{
    "username": "string",
    "password": "string",
    "roles": ["ROLE_API"],
    "generate_api_key": true
}
```

---

## Équipes (Teams)

### GET /api/teams

Récupère toutes les équipes ou une équipe spécifique.

**Paramètres de requête:**

- `id` (optionnel): ID de l'équipe spécifique

**Réponse (200):**

```json
[
    {
        "id": 1,
        "name": "Nom de l'équipe",
        "captain": "Nom du capitaine",
        "captain_id": 2
    }
]
```

### GET /api/teams/details

Récupère les détails complets d'une équipe avec joueurs et statistiques.

**Paramètres de requête:**

- `team_id` (requis): ID de l'équipe

**Réponse (200):**

```json
{
    "team": {
        "id": 1,
        "name": "Nom de l'équipe",
        "captain": "Nom du capitaine",
        "captain_id": 2
    },
    "players": [
        {
            "id": 1,
            "name": "Nom du joueur",
            "discord": "nom_discord"
        }
    ],
    "stats": [
        {
            "division_name": "Division A",
            "season_name": "Saison 2024",
            "position": 1,
            "total_teams": 8,
            "wins": 5,
            "losses": 2,
            "ties": 1,
            "winRounds": 15,
            "looseRounds": 8,
            "points": 16
        }
    ],
    "players_count": 5,
    "divisions_count": 2
}
```

### POST /api/teams

Crée une nouvelle équipe.

**Body (JSON):**

```json
{
    "name": "Nom de l'équipe"
}
```

### PUT /api/teams

Met à jour une équipe existante.

**Paramètres de requête:**

- `id` (requis): ID de l'équipe

**Body (JSON):**

```json
{
    "name": "Nouveau nom",
    "captain": 2
}
```

### PATCH /api/teams

Met à jour partiellement une équipe.

**Paramètres de requête:**

- `id` (requis): ID de l'équipe

**Body (JSON):**

```json
{
    "name": "Nouveau nom"
}
```

### DELETE /api/teams

Supprime une équipe.

**Paramètres de requête:**

- `id` (requis): ID de l'équipe

---

## Joueurs (Players)

### GET /api/players

Récupère tous les joueurs ou un joueur spécifique.

**Paramètres de requête:**

- `id` (optionnel): ID du joueur spécifique
- `team` (optionnel): ID de l'équipe pour filtrer

**Réponse (200):**

```json
[
    {
        "id": 1,
        "name": "Nom du joueur",
        "discord": "nom_discord",
        "team_id": 1,
        "team_name": "Nom de l'équipe"
    }
]
```

### POST /api/players

Crée un nouveau joueur.

**Body (JSON):**

```json
{
    "name": "Nom du joueur",
    "discord": "nom_discord",
    "team": 1
}
```

### PUT /api/players

Met à jour un joueur existant.

**Paramètres de requête:**

- `id` (requis): ID du joueur

**Body (JSON):**

```json
{
    "name": "Nouveau nom",
    "discord": "nouveau_discord",
    "team": 2
}
```

### PATCH /api/players

Met à jour partiellement un joueur.

**Paramètres de requête:**

- `id` (requis): ID du joueur

### DELETE /api/players

Supprime un joueur.

**Paramètres de requête:**

- `id` (requis): ID du joueur

---

## Matchs (Games)

### GET /api/games

Récupère les matchs avec filtrage optionnel.

**Paramètres de requête:**

- `id` (optionnel): ID du match spécifique
- `division_id` (optionnel): ID de la division
- `team_id` (optionnel): ID de l'équipe

**Réponse (200):**

```json
[
    {
        "id": 1,
        "date": "2024-08-28 20:00:00",
        "week": 1,
        "team1": "Équipe A",
        "team2": "Équipe B",
        "score1": 2,
        "score2": 1,
        "winner": 1,
        "status": "joué",
        "division": "Division A"
    }
]
```

### POST /api/games

Crée un nouveau match.

**Body (JSON):**

```json
{
    "date": "2024-08-28T20:00:00",
    "week": 1,
    "team1": 1,
    "team2": 2,
    "score1": 0,
    "score2": 0,
    "status": 1,
    "division": 1
}
```

### PUT /api/games

Met à jour un match existant.

**Paramètres de requête:**

- `id` (requis): ID du match

### PATCH /api/games

Met à jour partiellement un match.

**Paramètres de requête:**

- `id` (requis): ID du match

### DELETE /api/games

Supprime un match.

**Paramètres de requête:**

- `id` (requis): ID du match

---

## Divisions

### GET /api/division

Récupère toutes les divisions ou une division spécifique.

**Paramètres de requête:**

- `id` (optionnel): ID de la division spécifique

**Réponse (200):**

```json
[
    {
        "id": 1,
        "name": "Division A",
        "season_id": 1,
        "season_name": "Saison 2024"
    }
]
```

### GET /api/division/season

Récupère les divisions d'une saison spécifique.

**Paramètres de requête:**

- `id` (requis): ID de la saison

### GET /api/division/teams

Récupère les équipes d'une division avec classement.

**Paramètres de requête:**

- `id` (requis): ID de la division

**Réponse (200):**

```json
[
    {
        "position": 1,
        "team": {
            "id": 1,
            "name": "Équipe A"
        },
        "players_count": 5,
        "wins": 8,
        "losses": 2,
        "ties": 0,
        "points": 24
    }
]
```

### GET /api/division/games

Récupère les matchs d'une division.

**Paramètres de requête:**

- `id` (requis): ID de la division

### GET /api/division/details

Récupère les détails complets d'une division.

**Paramètres de requête:**

- `division_id` (requis): ID de la division

### POST /api/division

Crée une nouvelle division.

### PUT /api/division

Met à jour une division existante.

### PATCH /api/division

Met à jour partiellement une division.

### DELETE /api/division

Supprime une division.

---

## Saisons (Seasons)

### GET /api/season

Récupère toutes les saisons ou une saison spécifique.

**Paramètres de requête:**

- `id` (optionnel): ID de la saison spécifique

**Réponse (200):**

```json
[
    {
        "id": 1,
        "name": "Saison 2024",
        "start_date": "2024-01-01",
        "end_date": "2024-12-31",
        "total_games": 48,
        "finished_games": 32,
        "percentage": 66.67
    }
]
```

### GET /api/season/teams

Récupère les équipes inscrites à une saison.

**Paramètres de requête:**

- `id` (requis): ID de la saison

### GET /api/season/pourcent

Récupère le pourcentage de matchs terminés pour une saison.

**Paramètres de requête:**

- `id` (requis): ID de la saison
- `decimal` (optionnel, défaut: 2): Nombre de décimales

**Réponse (200):**

```json
{
    "id": 1,
    "name": "Saison 2024",
    "total": 48,
    "finished": 32,
    "pourcent": "66.67"
}
```

### POST /api/season

Crée une nouvelle saison.

### PUT /api/season

Met à jour une saison existante.

### PATCH /api/season

Met à jour partiellement une saison.

### DELETE /api/season

Supprime une saison.

---

## Statistiques d'équipes (Team Stats)

### GET /api/teamStats

Récupère les statistiques d'équipes avec filtrage.

**Paramètres de requête:**

- `team_id` (optionnel): ID de l'équipe
- `division_id` (optionnel): ID de la division

**Réponse (200):**

```json
[
    {
        "id": 1,
        "team_id": 1,
        "team_name": "Équipe A",
        "division_id": 1,
        "division_name": "Division A",
        "season_id": 1,
        "season_name": "Saison 2024",
        "wins": 8,
        "losses": 2,
        "ties": 0,
        "winRounds": 24,
        "looseRounds": 8,
        "points": 24
    }
]
```

### POST /api/teamStats

Crée de nouvelles statistiques d'équipe.

**Body (JSON):**

```json
{
    "team": 1,
    "division": 1,
    "wins": 0,
    "losses": 0,
    "ties": 0,
    "winRounds": 0,
    "looseRounds": 0,
    "points": 0
}
```

### PUT /api/teamStats

Met à jour les statistiques d'une équipe.

### PATCH /api/teamStats

Met à jour partiellement les statistiques.

### DELETE /api/teamStats

Supprime les statistiques d'une équipe.

---

## Statuts de match (Game Status)

### GET /api/gameStatus

Récupère tous les statuts de match ou un statut spécifique.

**Paramètres de requête:**

- `id` (optionnel): ID du statut spécifique

**Réponse (200):**

```json
[
    {
        "id": 1,
        "name": "programmé"
    },
    {
        "id": 2,
        "name": "joué"
    }
]
```

### POST /api/gameStatus

Crée un nouveau statut de match.

### PUT /api/gameStatus

Met à jour un statut existant.

### PATCH /api/gameStatus

Met à jour partiellement un statut.

### DELETE /api/gameStatus

Supprime un statut.

---

## Inscriptions (Registrations)

### GET /api/registrations

Récupère les inscriptions avec filtrage.

**Paramètres de requête:**

- `id` (optionnel): ID de l'inscription
- `season_id` (optionnel): ID de la saison
- `team_id` (optionnel): ID de l'équipe

**Réponse (200):**

```json
[
    {
        "id": 1,
        "season": "Saison 2024",
        "team": "Équipe A"
    }
]
```

### POST /api/registrations

Crée une nouvelle inscription.

**Body (JSON):**

```json
{
    "season": 1,
    "team": 1
}
```

### PUT /api/registrations

Met à jour une inscription existante.

### PATCH /api/registrations

Met à jour partiellement une inscription.

### DELETE /api/registrations

Supprime une inscription.

---

## Codes de réponse

- **200 OK**: Requête réussie
- **201 Created**: Ressource créée avec succès
- **400 Bad Request**: Erreur dans la requête (paramètres manquants, format JSON invalide)
- **401 Unauthorized**: Authentification requise ou token invalide
- **403 Forbidden**: Permissions insuffisantes
- **404 Not Found**: Ressource non trouvée
- **500 Internal Server Error**: Erreur serveur

---

## Modèles de données

### Team (Équipe)

```json
{
    "id": "integer",
    "name": "string",
    "captain": "string|null",
    "captain_id": "integer|null"
}
```

### Player (Joueur)

```json
{
    "id": "integer",
    "name": "string",
    "discord": "string|null",
    "team_id": "integer|null",
    "team_name": "string|null"
}
```

### Game (Match)

```json
{
    "id": "integer",
    "date": "string (Y-m-d H:i:s)|null",
    "week": "integer|null",
    "team1": "string|null",
    "team2": "string|null",
    "score1": "integer|null",
    "score2": "integer|null",
    "winner": "integer|null",
    "status": "string|null",
    "division": "string|null"
}
```

### Division

```json
{
    "id": "integer",
    "name": "string",
    "season_id": "integer",
    "season_name": "string"
}
```

### Season (Saison)

```json
{
    "id": "integer",
    "name": "string",
    "start_date": "string|null",
    "end_date": "string|null"
}
```

### TeamStat (Statistiques d'équipe)

```json
{
    "id": "integer",
    "team_id": "integer",
    "team_name": "string",
    "division_id": "integer",
    "division_name": "string",
    "season_id": "integer",
    "season_name": "string",
    "wins": "integer",
    "losses": "integer",
    "ties": "integer",
    "winRounds": "integer",
    "looseRounds": "integer",
    "points": "integer"
}
```

### GameStatus (Statut de match)

```json
{
    "id": "integer",
    "name": "string"
}
```

### Registration (Inscription)

```json
{
    "id": "integer",
    "season": "string",
    "team": "string"
}
```

### User (Utilisateur)

```json
{
    "id": "integer",
    "username": "string",
    "roles": "array",
    "is_active": "boolean"
}
```

---

## Notes techniques

1. **Authentification**: L'API utilise JWT (JSON Web Tokens) pour l'authentification
2. **Base de données**: Symfony avec Doctrine ORM
3. **Format de réponse**: JSON exclusivement
4. **Encodage**: UTF-8
5. **Sécurité**: Les endpoints de modification nécessitent une authentification appropriée

## Exemples d'utilisation

### Récupérer le classement d'une division

```bash
curl -X GET "/api/division/teams?id=1" \
  -H "Authorization: Bearer {token}"
```

### Créer une nouvelle équipe

```bash
curl -X POST "/api/teams" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -d '{"name": "Nouvelle Équipe"}'
```

### Mettre à jour le score d'un match

```bash
curl -X PATCH "/api/games?id=1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -d '{"score1": 2, "score2": 1, "winner": 1}'
```

---

*Documentation générée le 28 août 2025*
