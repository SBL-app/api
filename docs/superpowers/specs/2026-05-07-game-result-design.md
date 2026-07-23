# Design : Saisie et validation des résultats de match (#34)

## Contexte

Action la plus fréquente de la ligue : chaque semaine, les équipes jouent et doivent soumettre leurs résultats. Le score représente des **maps gagnées** (ex: 2-1 en BO3).

Flux retenu : soumission manuelle → double validation → dispute admin si contestation. Le timeout auto-dispute (scheduler) est traité dans le ticket #41.

## Entité : `GameResult`

```
GameResult
├── id: int
├── game: Game (ManyToOne, unique sur statuts actifs)
├── submittedByTeam: Team (ManyToOne) — équipe qui soumet
├── submittedBy: User (ManyToOne) — capitaine soumetteur
├── score1: int — maps gagnées par team1
├── score2: int — maps gagnées par team2
├── status: 'pending_validation' | 'confirmed' | 'disputed'
├── respondedBy: User (nullable) — capitaine qui confirme/conteste
├── respondedAt: DateTime (nullable)
└── createdAt: DateTimeImmutable
```

**Contraintes :**
- Un seul `GameResult` avec statut `pending_validation` par `Game` à la fois
- Si nouveau résultat soumis après dispute → le précédent reste archivé (disputed), nouveau créé
- Seul le capitaine de l'équipe adverse (pas le soumetteur) peut confirmer/contester

## Endpoints

| Méthode | Route | Accès | Description |
|---------|-------|-------|-------------|
| `POST` | `/api/games/{id}/result` | Capitaine team1 ou team2 | Soumettre un score |
| `PUT` | `/api/games/{id}/result/confirm` | Capitaine équipe adverse | Confirmer le score |
| `PUT` | `/api/games/{id}/result/dispute` | Capitaine équipe adverse | Contester le score |
| `PUT` | `/api/games/{id}/result/admin-resolve` | `ROLE_ADMIN` | Trancher un dispute |
| `GET` | `/api/games/{id}/result` | Public | Résultat en cours |

**Body POST / admin-resolve :**
```json
{ "score1": 2, "score2": 1 }
```

**Règles d'accès :**
- `POST` : game ne doit pas être déjà `played`, pas de `pending_validation` existant
- `PUT confirm/dispute` : uniquement le capitaine de l'équipe qui n'a **pas** soumis
- `PUT admin-resolve` : `ROLE_ADMIN`, game doit avoir un résultat `disputed`

## Mise à jour des stats à la confirmation

Déclenchée par `confirm` et `admin-resolve`. Mise à jour directe dans le controller (pas d'event).

**Game :** `score1`, `score2`, `winner` (1 / 2 / null si égalité), `status` → `"played"`

**TeamStat (les 2 équipes dans la division) :**

| Champ | Vainqueur | Perdant | Égalité |
|-------|-----------|---------|---------|
| `wins` | +1 | — | — |
| `losses` | — | +1 | — |
| `ties` | — | — | +1 |
| `points` | +3 | +0 | +1 |
| `winRounds` | +score gagné | +score gagné | idem |
| `looseRounds` | +score perdu | +score perdu | idem |

Exemple : score 2-1 → team1 gagne : `wins+1, points+3, winRounds+2, looseRounds+1`

Si `TeamStat` absente pour une équipe dans la division → `ApiProblemException::badRequest`.

## Hors scope (#41)

- Timeout auto-dispute via Symfony Scheduler (si pas de réponse dans X jours)
