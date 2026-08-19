# Journal des versions — API SBL

Toutes les évolutions notables de l'API Symfony sont consignées dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le
versionnage respecte le [Semantic Versioning](https://semver.org/lang/fr/).

> **Note sur la reconstitution.** Le projet a démarré sans convention de commit
> ni tags SemVer. Les versions `0.1.0` à `0.5.0` ont donc été reconstituées a
> posteriori à partir de l'historique Git (plus de 180 commits depuis le
> 03/05/2024), en retenant comme jalons les incréments effectivement livrés et
> validés avec le staff SBL lors des points mensuels. Les versions `1.0.0` et
> suivantes correspondent à des **tags Git réels** posés sur la branche `main`.
> À partir de la version `2.0.0`, ce fichier est généré automatiquement par
> `git-cliff` à chaque release (voir `.github/workflows/release.yml`).

---

## [Non publié]

Développé sur la branche `dev`, en attente de fusion vers `main`.

### Ajouté

- **Playoffs** : entités `Bracket` et `BracketEntry`, génération de brackets à
  élimination simple avec gestion des byes et de la petite finale
  (`BracketGeneratorService`)
- **Têtes de série** : `BracketSeedingService` détermine les seeds depuis le
  classement de division
- **Progression automatique** : `BracketProgressionService` fait avancer les
  vainqueurs, déclenché depuis le flux de résultat et depuis le forfait
- `BracketController` — CRUD, seeding et génération
- Clôture automatique des saisons et divisions lorsque tous les matchs sont
  joués (#32)
- Import des résultats de la saison 3

### Corrigé

- Contrôle administrateur manquant sur `createBracket`
- Garde contre la re-résolution d'un match aval déjà joué
- Nettoyage du schéma de test via `dropDatabase()`, plus robuste que
  `dropSchema()` face aux clés étrangères auto-référentes de `Game`

---

## [2.0.0] — 2026-07-23

**Version majeure.** Refonte du backend introduisant l'authentification sur
l'ensemble des endpoints d'écriture. Le contrat de l'API change pour ses deux
clients — frontend Vue et bot Discord — qui doivent être déployés de façon
coordonnée. C'est précisément ce qu'un incrément majeur signale dans ce projet.

Périmètre : 261 fichiers modifiés, environ 30 000 lignes ajoutées.

### Ajouté

- **Authentification** : JWT (LexikJWTAuthenticationBundle), OAuth Discord,
  clés d'API à expiration pour les accès de service (#23)
- **Gestion des résultats de match** : soumission par une équipe, confirmation
  par l'équipe adverse, passage automatique en litige après délai (#33, #2)
- **Notifications** : notifications push Web (VAPID), rappels et échéances
  automatiques pilotés par le scheduler Symfony (#24)
- **Comptes rendus de match** et statut de jeu `reported`
- **Membres d'équipe** : entité `TeamMember` et endpoints associés
- Calcul automatique des `TeamStat` après validation d'un résultat
- Propositions de match (`MatchProposal`) et génération du calendrier de
  division
- `GET /games/unscheduled` — matchs non planifiés
- `GET /season/current/week` et `PATCH /games/{id}/schedule`
- Journalisation applicative structurée (Monolog, canal `app`) et alertes
  e-mail sur erreur en production

### Modifié

- **Migration du SGBD de MySQL vers PostgreSQL** — décision prise pour
  s'affranchir de la licence propriétaire Oracle et préparer la montée en
  charge
- Conteneurisation complète de la production (Docker, Traefik, PHP 8.3)

### Sécurité

- Endpoints `POST`, `PUT`, `PATCH` et `DELETE` désormais soumis à
  authentification
- Suppression d'un fichier d'identifiants du dépôt et remplacement des clés
  push d'exemple

---

## [1.1.0] — 2026-07-19

Version consacrée à la qualité et à l'outillage, sans évolution fonctionnelle
visible par les utilisateurs.

### Ajouté

- **Chaîne d'intégration continue** : exécution de PHPUnit, analyse statique
  PHPStan et vérification de style PHP CS-Fixer sur chaque pull request
- Durcissement de la configuration selon les recommandations OWASP
- Couverture de test des endpoints d'équipes de saison et du pourcentage de
  matchs joués

### Sécurité

- `symfony/process` 7.3.0 → 7.4.5 (Dependabot)
- `symfony/http-foundation` 7.3.0 → 7.4.1 (Dependabot)

---

## [1.0.0] — 2025-06-26

**Première mise en production.** L'API quitte l'environnement de développement
et sert le site public de la ligue.

### Ajouté

- Fichiers de configuration nécessaires au déploiement

### Corrigé

- Configuration CORS et route racine par défaut
- Corrections diverses bloquant le déploiement

### Sécurité

- Désactivation des routes `POST`, `PUT`, `PATCH` et `DELETE` en attendant la
  mise en place de l'authentification, livrée en `2.0.0`

---

## [0.5.0] — 2025-04-12

### Ajouté

- Champs complémentaires sur `TeamStat`
- Configuration Apache / cPanel pour le premier hébergement

### Modifié

- Présentation des informations d'équipe

### Corrigé

- Calcul et filtrage du pourcentage de matchs joués

---

## [0.4.0] — 2024-12-21

### Ajouté

- **Inscriptions** : entité `Registration` et contrôleur associé

### Modifié

- `getSeasonTeams` s'appuie désormais sur le dépôt `Registration`

### Corrigé

- Autorisation des champs nuls à la création d'un match

---

## [0.3.0] — 2024-11-10

### Ajouté

- `getSeasonGames`, `getSeasonTeams`, `getSeasonGamesByStatus`
- `getFinishedMatchPourcent` — taux d'avancement d'une saison
- Récupération des matchs par division

### Modifié

- Structure des réponses uniformisée

### Corrigé

- Erreur de calcul sur `totalGames` dans le pourcentage d'avancement

---

## [0.2.0] — 2024-07-15

### Ajouté

- `getTeamStatByDivision`, `getPlayerByTeam`, `getGamesByTeam`
- `getDivisionBySeason` enrichi des informations d'équipe

### Modifié

- Revue complète du nommage des routes et de leurs arguments (#2)
- Passage à `EntityManagerInterface` dans les contrôleurs

### Corrigé

- Erreurs CORS bloquant les appels depuis le frontend

---

## [0.1.0] — 2024-05-16

Première version fonctionnelle de l'API.

### Ajouté

- Entités `Team`, `TeamStat`, `Division`, `Season`, `Player`, `Game`,
  `GameStatus` et leurs contrôleurs CRUD
- Relation entre `Game` et `Division`
- Jeux de données de développement (Faker)
- Documentation initiale

---

[Non publié]: https://github.com/SBL-app/api/compare/v2.0.0...dev
[2.0.0]: https://github.com/SBL-app/api/releases/tag/v2.0.0
[1.1.0]: https://github.com/SBL-app/api/releases/tag/v1.1.0
[1.0.0]: https://github.com/SBL-app/api/releases/tag/v1.0.0
