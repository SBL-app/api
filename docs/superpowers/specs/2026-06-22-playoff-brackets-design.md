# Design — Brackets de playoff

**Date** : 2026-06-22
**Statut** : Validé (prêt pour plan d'implémentation)

## Objectif

Permettre de créer des arbres de playoff (brackets) pour les divisions. Les règles
de victoire sont identiques à celles des matchs de saison régulière. Le placement
dans l'arbre se fait selon un *seed* déterminé par les résultats de la saison
régulière. La solution doit aussi être utilisable pour des tournois détachés des
saisons régulières.

## Décisions de cadrage

| Décision | Choix retenu |
|---|---|
| Format V1 | Simple élimination + match pour la 3e place (petite finale) |
| Confrontation | 1 match unique par confrontation (pas de best-of) |
| Entité match | Réutiliser l'entité `Game` existante |
| Qualification | Top N configurable (`qualifiedCount`) |
| Portée | 1 bracket ↔ 1 division ; l'admin crée des brackets pour les divisions voulues. Pas de seeding cross-division. |
| Seeding | Figé à la génération (snapshot du classement persisté en `BracketEntry`) |

## Vue d'ensemble

Nouveau concept **`Bracket`** : conteneur d'un arbre, découplé de la saison. Il
réutilise l'entité `Game` et l'ensemble du flux de résultat existant
(`GameResult` : soumission + double validation + contestation + forfait). Un
service de progression fait avancer les vainqueurs (et les perdants des demi-finales
vers la petite finale).

Un tournoi détaché est simplement un `Bracket` sans `division` liée, dont les seeds
sont posés manuellement.

## Modèle de données

### Entité `Bracket` (nouvelle)

| champ | type | rôle |
|---|---|---|
| `id` | int | PK |
| `name` | string(255) | ex. "Playoff Division 1 S3" |
| `format` | string (enum) | `single_elimination` (extensible) |
| `hasThirdPlaceMatch` | bool (default false) | active la petite finale |
| `qualifiedCount` | int | nombre d'équipes qualifiées (top N) |
| `division` | FK `Division` **nullable** | présent = playoff de saison ; null = tournoi détaché |
| `status` | string (enum) | `draft` → `ready` → `in_progress` → `completed` |

### Entité `BracketEntry` (nouvelle) — snapshot du seeding

| champ | type | rôle |
|---|---|---|
| `id` | int | PK |
| `bracket` | FK `Bracket` (non-null) | |
| `seed` | int | rang 1..N |
| `team` | FK `Team` (non-null) | équipe qualifiée |

Les seeds sont figés à la génération. Si `Bracket.division` est liée, ils sont
auto-remplis depuis les `TeamStat` triés par classement (points décroissants, même
ordre que `DivisionController`). Si détaché, ils sont posés manuellement via l'API.

### Entité `Game` (étendue)

Modifications sur l'entité existante :

- `division` → **devient nullable**. Contrainte applicative : un `Game` appartient
  soit à une `division` (match régulier), soit à un `bracket` (match de playoff),
  jamais aux deux ni à aucun.
- `week` → **devient nullable** (pas de notion de semaine en bracket).

Nouveaux champs :

| champ | type | rôle |
|---|---|---|
| `bracket` | FK `Bracket` nullable | rattachement playoff |
| `round` | int nullable | 1 = premier tour … valeur la plus haute = finale |
| `bracketPosition` | int nullable | position du match dans son round |
| `isThirdPlaceMatch` | bool (default false) | match de la petite finale |
| `winnerToGame` | self-FK `Game` nullable | match où va le vainqueur |
| `winnerToSlot` | int nullable (1\|2) | slot cible du vainqueur |
| `loserToGame` | self-FK `Game` nullable | match où va le perdant (demi-finales → petite finale) |
| `loserToSlot` | int nullable (1\|2) | slot cible du perdant |

Réutilisés tels quels : `score1/score2`, `winner`, `status` (`GameStatus`),
`isForfeit`/`forfeitTeam`/`forfeitReason`, et le flux `GameResult`.

## Algorithme de génération de l'arbre

`POST /api/brackets/{id}/generate` :

1. Charge les `BracketEntry` (seeds). N qualifiés.
2. Calcule la taille de bracket = puissance de 2 ≥ N. La différence donne le nombre
   de **byes**, attribués aux meilleurs seeds (ils avancent automatiquement au
   tour 1).
3. Pairing standard simple élimination : seed 1 vs N, 2 vs N-1, etc., avec
   placement classique (« 1 en haut de l'arbre, 2 en bas ») de sorte que les deux
   meilleurs seeds ne puissent se rencontrer qu'en finale.
4. Crée les `Game` de chaque round : sans date, statut `scheduled`,
   `division = null`, `bracket` lié, `round` et `bracketPosition` renseignés.
5. Câble `winnerToGame` / `winnerToSlot` de chaque match vers le match du round
   suivant.
6. Si `hasThirdPlaceMatch` : crée le match de 3e place (`isThirdPlaceMatch = true`)
   et câble `loserToGame` / `loserToSlot` des deux demi-finales vers ce match.
7. Byes : les matchs du tour 1 réduits à une seule équipe sont résolus
   immédiatement (le vainqueur est propagé au tour suivant).
8. `Bracket.status` passe à `ready` (ou `in_progress`).

## Progression (réutilise les règles de victoire)

Hook déclenché après qu'un `GameResult` rattaché à un match de bracket passe au
statut `played` (le `winner` est calculé par le flux existant, aucune logique de
score dupliquée) :

- `BracketProgressionService` place le `winner` dans `winnerToGame` au slot
  `winnerToSlot`.
- Si `loserToGame` est défini, place le perdant dans `loserToGame` au slot
  `loserToSlot`.
- Quand les deux slots (`team1`, `team2`) d'un match suivant sont remplis, le match
  devient jouable (date à proposer via le flux normal).
- Quand la finale est jouée, `Bracket.status` passe à `completed`.

## Réutilisation des flux existants

- **Dates** : `MatchProposal` fonctionne tel quel (un `Game` sans date → les
  capitaines proposent une date).
- **Résultats** : `GameResult` submit / validate / contest / admin-validate
  inchangés.
- **Forfait** : `isForfeit` (score 4-0 automatique) fonctionne.
- **Clôture saison / stats** : les `Game` de bracket ont `division = null`, donc ils
  sont naturellement exclus du recalcul des `TeamStat` et de l'auto-clôture de
  saison/division (issue #32). Aucune régression sur la logique régulière.
- **Push** : les notifications de résultat fonctionnent ; ajout possible plus tard
  d'une notification « vous accédez au tour suivant ».

## Endpoints (`BracketController`)

```
POST   /api/brackets                  créer (name, format, division?, qualifiedCount, hasThirdPlaceMatch)
GET    /api/brackets/{id}             arbre complet (rounds, matchs, seeds)
GET    /api/divisions/{id}/bracket    bracket d'une division
POST   /api/brackets/{id}/seed        auto-seed depuis le classement de la division (fige les BracketEntry)
PUT    /api/brackets/{id}/entries     poser des seeds manuels (tournoi détaché)
POST   /api/brackets/{id}/generate    construit l'arbre (Games + câblage progression)
DELETE /api/brackets/{id}             supprime le bracket et ses Games
```

Les matchs eux-mêmes (date, soumission/validation de résultat) passent par les
contrôleurs existants `GameController` / `GameResultController` / `MatchProposalController`.

Permissions : création / seed / génération / suppression réservées à `ROLE_ADMIN`
(via `SecuredControllerTrait`). Lecture publique comme le reste des `GET /api/*`.

## Tournoi détaché

Un `Bracket` avec `division = null`, statut `draft`. L'admin pose les
`BracketEntry` manuellement (`PUT /api/brackets/{id}/entries`) puis appelle
`generate`. Tout le reste est identique au playoff de division. Aucune dépendance
à une saison ou à une division.

## Migration & tests

- **Migration Doctrine** :
  - `game.division_id` → nullable
  - `game.week` → nullable
  - ajout colonnes `Game` : `bracket_id`, `round`, `bracket_position`,
    `is_third_place_match`, `winner_to_game_id`, `winner_to_slot`,
    `loser_to_game_id`, `loser_to_slot`
  - création tables `bracket` et `bracket_entry`
  - compatible avec les données saison 3 existantes (les colonnes deviennent
    nullable, valeurs existantes conservées)
- **Tests** :
  - Unit : algorithme de génération (pairing seeds, byes non-puissance-de-2,
    câblage petite finale), `BracketProgressionService` (avancée
    vainqueur/perdant).
  - Functional : `generate`, auto-seed depuis classement, progression de bout en
    bout (du tour 1 à la finale + 3e place), scénario tournoi détaché.

## Hors périmètre V1 (YAGNI)

- Double élimination (winner/loser brackets).
- Séries best-of (plusieurs matchs par confrontation).
- Seeding cross-division.
- Reseeding dynamique après génération.
