# Guide d'Authentification API SBL

Ce guide explique comment utiliser le système d'authentification de l'API SBL pour sécuriser l'accès aux données.

## Aperçu du Système

L'API utilise un système d'authentification **JWT (JSON Web Token)** robuste avec support pour les clés API pour permettre un accès sécurisé aux opérations de modification des données (POST, PUT, PATCH, DELETE).

### Caractéristiques principales :

- **Authentification JWT** : Tokens sécurisés avec expiration automatique
- **Clés API** : Alternative pour les applications et bots
- **Refresh Tokens** : Prolongation automatique de session
- **Gestion des rôles** : Contrôle granulaire des permissions
- **Sécurité renforcée** : Protection contre les accès non autorisés

## Routes d'Authentification

### 1. Connexion avec nom d'utilisateur/mot de passe

```bash
POST /api/auth/login
Content-Type: application/json

{
    "username": "votre_nom_utilisateur",
    "password": "votre_mot_de_passe"
}
```

**Réponse :**
```json
{
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
    "user": {
        "id": 1,
        "username": "votre_nom_utilisateur",
        "roles": ["ROLE_API"],
        "last_login": "2025-08-26 14:30:00",
        "is_active": true
    },
    "expires_in": 3600
}
```

### 2. Connexion avec clé API

```bash
POST /api/auth/login-api-key
Content-Type: application/json

{
    "api_key": "votre_cle_api_64_caracteres"
}
```

**Réponse :**
```json
{
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
    "user": {
        "id": 1,
        "username": "bot_user",
        "roles": ["ROLE_API"],
        "last_login": "2025-08-26 14:30:00",
        "is_active": true
    },
    "expires_in": 3600,
    "login_method": "api_key"
}
```

### 3. Actualisation du token

```bash
POST /api/auth/refresh
Authorization: Bearer votre_token_jwt
```

**Réponse :**
```json
{
    "token": "nouveau_token_jwt...",
    "user": {
        "id": 1,
        "username": "votre_nom_utilisateur",
        "roles": ["ROLE_API"],
        "last_login": "2025-08-26 14:30:00",
        "is_active": true
    },
    "expires_in": 3600
}
```

### 4. Vérification du token

```bash
POST /api/auth/verify
Authorization: Bearer votre_token_jwt
```

**Réponse :**
```json
{
    "valid": true,
    "user": {
        "id": 1,
        "username": "votre_nom_utilisateur",
        "roles": ["ROLE_API"],
        "last_login": "2025-08-26 14:30:00",
        "is_active": true
    },
    "expires_at": "2025-08-26 15:30:00"
}
```

### 5. Profil utilisateur

```bash
GET /api/auth/me
Authorization: Bearer votre_token_jwt
```

## Utilisation dans les Requêtes

### Avec Token JWT (Recommandé)

```bash
# Créer une nouvelle saison
POST /api/season
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
Content-Type: application/json

{
    "name": "Saison 2025-2026",
    "start_date": "2025-09-01",
    "end_date": "2026-06-30"
}
```

### Avec Clé API (Alternative)

```bash
# Créer une nouvelle saison avec clé API
POST /api/season
X-API-KEY: votre_cle_api_64_caracteres
Content-Type: application/json

{
    "name": "Saison 2025-2026",
    "start_date": "2025-09-01",
    "end_date": "2026-06-30"
}
```

## Endpoints API Disponibles

Tous les endpoints de l'API sont préfixés par `/api` pour une meilleure organisation :

### Authentification

- `POST /api/auth/login` - Connexion avec username/password
- `POST /api/auth/login-api-key` - Connexion avec clé API
- `POST /api/auth/refresh` - Actualisation du token
- `POST /api/auth/verify` - Vérification du token
- `GET /api/auth/me` - Profil utilisateur

### Ressources principales

- `GET|POST|PUT|PATCH|DELETE /api/season` - Gestion des saisons
- `GET|POST|PUT|PATCH|DELETE /api/division` - Gestion des divisions
- `GET|POST|PUT|PATCH|DELETE /api/teams` - Gestion des équipes
- `GET|POST|PUT|PATCH|DELETE /api/players` - Gestion des joueurs
- `GET|POST|PUT|PATCH|DELETE /api/games` - Gestion des matchs
- `GET|POST|PUT|PATCH|DELETE /api/gameStatus` - Gestion des statuts de match
- `GET|POST|PUT|PATCH|DELETE /api/registrations` - Gestion des inscriptions
- `GET|POST|PUT|PATCH|DELETE /api/teamStats` - Gestion des statistiques d'équipe

## Gestion des Utilisateurs via CLI

L'API inclut une commande CLI pour gérer facilement les utilisateurs :

### Créer un utilisateur

```bash
# Créer un utilisateur avec accès API
php bin/console app:auth:manage-user create dev_user --password=secret --roles=ROLE_API --api-key

# Créer un administrateur
php bin/console app:auth:manage-user create admin_user --password=admin123 --roles=ROLE_ADMIN,ROLE_API
```

### Générer un token

```bash
php bin/console app:auth:manage-user token dev_user
```

### Lister les utilisateurs

```bash
php bin/console app:auth:manage-user list
```

### Activer/Désactiver un utilisateur

```bash
# Désactiver un utilisateur
php bin/console app:auth:manage-user deactivate old_user

# Réactiver un utilisateur
php bin/console app:auth:manage-user activate old_user
```

## Rôles et Permissions

### Rôles disponibles

- **ROLE_USER** : Accès de base (attribué automatiquement)
- **ROLE_API** : Permet les opérations de modification (POST, PUT, PATCH, DELETE)
- **ROLE_ADMIN** : Accès complet (futur développement)

### Règles d'accès

- **Lecture (GET)** : Accessible publiquement pour la plupart des routes
- **Modification (POST, PUT, PATCH, DELETE)** : Nécessite `ROLE_API` ou `ROLE_ADMIN`
- **Routes d'authentification** : Accès libre pour `/login` et `/login-api-key`
- **Autres routes d'auth** : Nécessite `ROLE_USER` minimum

## Sécurité et Bonnes Pratiques

### Durée de vie des tokens

- **Token JWT** : 1 heure (3600 secondes)
- **Refresh possible** : Jusqu'à 24 heures après expiration
- **Clé API** : Pas d'expiration (révocation manuelle)

### Recommandations de sécurité

1. **Utilisez HTTPS** en production
2. **Stockez les clés API de manière sécurisée**
3. **Régénérez les clés API périodiquement**
4. **Surveillez les accès via les logs**
5. **Désactivez les comptes inutilisés**

### Configuration des clés JWT

Les clés JWT sont stockées dans le dossier `config/jwt/` :

- `private.pem` : Clé privée pour signer les tokens
- `public.pem` : Clé publique pour vérifier les tokens

## Gestion d'Erreurs

### Erreurs courantes

```json
// Token manquant ou invalide
{
    "error": "Missing or invalid Authorization header"
}

// Permissions insuffisantes
{
    "error": "Insufficient permissions for data modification"
}

// Token expiré
{
    "error": "Invalid or expired token"
}

// Clé API invalide
{
    "error": "Invalid API key or access not authorized"
}
```

## Exemples d'Intégration

### JavaScript/Fetch

```javascript
// Connexion
const loginResponse = await fetch('/api/auth/login', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        username: 'dev_user',
        password: 'secret'
    })
});

const { token } = await loginResponse.json();

// Utilisation du token
const response = await fetch('/api/season', {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        name: 'Nouvelle saison',
        start_date: '2025-09-01',
        end_date: '2026-06-30'
    })
});
```

### PHP/cURL

```php
// Connexion
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.sbl.com/api/auth/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'username' => 'dev_user',
    'password' => 'secret'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$data = json_decode($response, true);
$token = $data['token'];

// Utilisation du token
curl_setopt($ch, CURLOPT_URL, 'https://api.sbl.com/api/season');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'name' => 'Nouvelle saison',
    'start_date' => '2025-09-01',
    'end_date' => '2026-06-30'
]));

$result = curl_exec($ch);
curl_close($ch);
```

## Support et Développement

Pour toute question ou problème lié à l'authentification :

1. Vérifiez les logs d'erreur Symfony
2. Utilisez la commande de vérification JWT : `php bin/console lexik:jwt:check-config`
3. Testez les routes avec un outil comme Postman ou curl
4. Consultez la documentation Symfony Security

## Mise à jour des URLs

**Important :** Toutes les routes API ont été mises à jour pour utiliser le préfixe `/api` pour une meilleure cohérence :

- ✅ **Nouvelle URL :** `/api/teams` (recommandée)
- ❌ **Ancienne URL :** `/teams` (non fonctionnelle)

Assurez-vous de mettre à jour tous vos appels API pour utiliser le préfixe `/api/` :

```bash
# Correct
GET /api/teams
POST /api/season
PUT /api/players?id=1

# Incorrect (ne fonctionne plus)
GET /teams
POST /season  
PUT /players?id=1
```

---

*Ce système d'authentification garantit la sécurité et l'intégrité des données de l'API SBL tout en restant simple d'utilisation pour les développeurs et les applications automatisées.*
