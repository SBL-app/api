# Gestion des Forfaits dans l'API SBL

## Aperçu

L'API SBL prend maintenant en charge la gestion des forfaits dans les matchs. Lorsqu'une équipe déclare forfait ou est déclarée forfait, le résultat du match est automatiquement défini comme un 4-0 en faveur de l'équipe adversaire.

## Nouveaux Champs

L'entité `Game` a été étendue avec les champs suivants :

- `is_forfeit` (boolean) : Indique si le match est un forfait
- `forfeit_team` (integer, nullable) : Quelle équipe a fait forfait (1 ou 2)
- `forfeit_reason` (string, nullable) : Raison du forfait

## Logique Automatique

Lorsqu'un forfait est déclaré :

1. Si `forfeit_team = 1` (Team1 forfait) :
   - `score1 = 0`, `score2 = 4`, `winner = 2`

2. Si `forfeit_team = 2` (Team2 forfait) :
   - `score1 = 4`, `score2 = 0`, `winner = 1`

## Utilisation de l'API

### Créer un match avec forfait

```json
POST /api/games
{
    "date": "2024-09-15 14:30:00",
    "week": 10,
    "team1": 1,
    "team2": 2,
    "status": 1,
    "division": 1,
    "is_forfeit": true,
    "forfeit_team": 1,
    "forfeit_reason": "Joueur blessé"
}
```

### Mettre à jour un match pour ajouter un forfait

```json
PATCH /api/games?id=123
{
    "is_forfeit": true,
    "forfeit_team": 2,
    "forfeit_reason": "Problème de transport"
}
```

### Réponse API avec forfait

```json
{
    "id": 123,
    "date": "2024-09-15 14:30:00",
    "week": 10,
    "team1": "Équipe A",
    "team2": "Équipe B",
    "score1": 0,
    "score2": 4,
    "winner": 2,
    "status": "Terminé",
    "division": "Division 1",
    "is_forfeit": true,
    "forfeit_team": 1,
    "forfeit_reason": "Joueur blessé"
}
```

## Cas d'Usage

### Forfait avant le match

Lorsqu'une équipe déclare forfait avant le début du match :

1. Créer le match avec `is_forfeit: true`
2. Spécifier l'équipe qui fait forfait avec `forfeit_team`
3. Ajouter la raison avec `forfeit_reason`

### Forfait pendant le match

Si une équipe abandonne pendant le match :

1. Utiliser PATCH pour mettre à jour le match existant
2. Activer `is_forfeit: true`
3. Spécifier l'équipe qui fait forfait

### Match normal

Les matchs normaux continuent de fonctionner comme avant :

- Ne pas inclure les champs de forfait ou les laisser à `null`/`false`
- Définir les scores manuellement

## Validation

- `forfeit_team` doit être 1, 2, ou null
- Si `is_forfeit` est true, `forfeit_team` doit être spécifié
- Les scores sont automatiquement calculés et ne peuvent pas être overridés manuellement pour les forfaits

## Migration de Base de Données

Une migration a été créée pour ajouter les nouveaux champs :

- `is_forfeit` : TINYINT(1) DEFAULT 0 NOT NULL
- `forfeit_team` : INT DEFAULT NULL
- `forfeit_reason` : LONGTEXT DEFAULT NULL

Pour appliquer la migration :

```bash
php bin/console doctrine:migrations:migrate
```

## Tests

Des tests unitaires ont été ajoutés pour vérifier :

- La logique automatique des scores en cas de forfait
- La gestion des équipes forfait
- La compatibilité avec les matchs normaux

Exécuter les tests :

```bash
php bin/phpunit tests/Unit/Entity/GameTest.php
```
