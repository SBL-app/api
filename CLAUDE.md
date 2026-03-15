# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Install & setup
make install-dev          # Install with dev dependencies
make setup-db             # Create DB + run migrations
make setup-test-db        # Prepare test DB (SQLite)

# Run tests
make test                 # All tests (PHPUnit)
make test-unit            # Unit tests only
make test-integration     # Integration tests only
make test-functional      # Functional tests only
make test-coverage        # HTML coverage in var/coverage/

# Run a single test file or method
php bin/phpunit tests/Functional/Controller/AuthControllerTest.php
php bin/phpunit --filter testLoginSuccess

# Code quality
make lint                 # PHP syntax check
make fix                  # Auto-fix code style (PHP CS Fixer)
make security             # Dependency vulnerability check

# Dev server
make dev-server           # PHP built-in server on localhost:8000
make clean                # Clear cache/logs/coverage
```

## Architecture

### Controller Hierarchy

All API controllers extend `BaseController` which provides:
- `getRequestData(Request)` — extracts and validates JSON request bodies
- `saveEntity()` / `deleteEntity()` — Doctrine persistence wrappers
- `findEntityOrFail()` — find entity by ID or throw ApiProblemException
- `securedCreateEntity()` / `securedUpdateEntity()` / `securedDeleteEntity()` — permission-checked persistence
- `formatEntityData()` — abstract method each controller implements to serialize its entity

`BaseController` uses `SecuredControllerTrait` (from `src/Security/`) which adds:
- `checkModificationPermissions()` — verifies write access
- `checkUserRole(string $role)` — role assertion
- `getAuthenticatedUser()` — returns the authenticated `User` entity

### Authentication & Security

**Multi-layer auth system** configured in `config/packages/security.yaml`:
- **JWT** (lexik bundle) — primary auth for `/api/*` routes
- **Discord OAuth** — user registration/login flow via `DiscordOAuthService`
- **API keys** — alternative auth with expiration for bot/service access

**Firewall order matters** — specific routes (`/api/auth/login`, `/api/auth/discord*`) have their own firewalls without JWT, before the catch-all `api` firewall.

**Access control rules:**
- GET `/api/*` → `PUBLIC_ACCESS` (read-only is public)
- POST/PUT/PATCH/DELETE `/api/*` → requires `ROLE_API`
- `/api/users/me`, `/api/teams/*/members` → requires `ROLE_USER`
- `/api/push/subscribe` → requires `ROLE_USER`

**`ApiAccessVoter`** (`src/Security/`) handles fine-grained access: `READ` for any authenticated user, `WRITE` for `ROLE_API` or `ROLE_ADMIN`.

### Error Handling

`ApiProblemException` (`src/Exception/`) provides factory methods: `notFound()`, `badRequest()`, `validationError()`, `forbidden()`, `unauthorized()`, `conflict()`, `tooManyRequests()`.

`ApiProblemExceptionListener` (`src/EventListener/`) catches exceptions on `/api` routes and returns Problem+JSON responses.

### Push Notifications

`PushNotificationService` (`src/Service/`) sends Web Push notifications via VAPID keys (minishlink/web-push).

**Scheduler** : `MatchReminderSchedule` runs hourly via Symfony Messenger to send match reminders 24h before game time. Docker service `scheduler` runs `messenger:consume scheduler_default`.

**Integration points** : notifications are sent from `MatchProposalController` (new/accepted/rejected proposals) and `DivisionController` (division finalization). All notification calls are wrapped in try/catch to avoid blocking main actions.

### Testing

- **Unit** (`tests/Unit/Entity/`, `tests/Unit/MessageHandler/`) — entity logic, handler logic with mocks
- **Integration** (`tests/Integration/Repository/`) — repository queries against SQLite
- **Functional** (`tests/Functional/Controller/`) — full HTTP request/response tests

`ApiTestCase` (`tests/Functional/ApiTestCase.php`) is the base class for functional tests with helpers: `jsonRequest()`, `cleanDatabase()`, `assertJsonResponseStructure()`.

Test environment uses SQLite (configured in `.env.test`).

### Key Services

- `AuthenticationService` (`src/Service/`) — JWT creation/validation, token revocation, API key validation
- `DiscordOAuthService` (`src/Service/`) — OAuth2 flow, Discord user sync, bot secret validation
- `PushNotificationService` (`src/Service/`) — Web Push notifications via VAPID, expired subscription cleanup

### Routing

All routes use PHP 8 attributes (`#[Route()]`) directly on controller methods. No YAML route definitions — `config/routes.yaml` just points to `src/Controller/` with type `attribute`.

### Entity Relationships

```
User ←→ TeamMember ←→ Team
Team → Player (captain)
Season → Division → Game (team1, team2, gameStatus)
Season → Registration → Team
Division → TeamStat → Team
Game ← MatchProposal → User (proposer, receiver)
Game ← MatchReport → Team (requested by User)
User ← PushSubscription (endpoint, VAPID keys)
```

## Features

### 1. Authentication (`AuthController`)
- Login by username/password → JWT token
- Login by API key (bot/service access)
- JWT token verify, refresh, logout (with token revocation)
- Discord OAuth2 flow (authorize, callback, user sync)
- Discord bot authentication via shared secret (`X-Bot-Secret` header)
- Rate limiting on auth endpoints

### 2. User Profile (`UserController`)
- `GET /api/users/me` — current user profile
- `GET /api/users/me/teams` — teams the user belongs to

### 3. Teams (`TeamController`)
- CRUD on teams (`/api/teams`)
- Team member management (`/api/teams/{id}/members`) — add, remove, change roles
- Roles: `ROLE_CAPTAIN`, `ROLE_MEMBER`
- Captain can manage team members

### 4. Players (`PlayerController`)
- CRUD on players (`/api/players`)

### 5. Seasons (`SeasonController`)
- CRUD on seasons (`/api/seasons`)
- `GET /api/seasons/current` — active season based on dates
- `GET /api/seasons/current/week` — current week number
- `GET /api/seasons/{id}/completion` — season completion stats
- `GET /api/seasons/{id}/teams` — teams registered for a season

### 6. Divisions (`DivisionController`)
- CRUD on divisions (`/api/divisions`)
- `GET /api/seasons/{id}/divisions` — divisions with team standings
- `GET /api/divisions/{id}/teams` — teams with players ranked by points
- `GET /api/divisions/{id}/games` — games grouped by week
- `GET /api/divisions/{id}/details` — full division view (ranking + games + teams)
- `POST /api/divisions/{id}/schedule` — generate round-robin or double round-robin schedule
- `PATCH /api/divisions/{id}` with `is_finalized: true` — finalize and notify all team members via push

### 7. Games (`GameController`)
- CRUD on games (`/api/games`)
- Filters: `?team_id=`, `?division_id=`, `?season_id=`, `?week=`
- Forfeit management: `is_forfeit`, `forfeit_team`, `forfeit_reason` with automatic 4-0 score

### 8. Game Statuses (`GameStatusController`)
- CRUD on game statuses (`/api/game-statuses`) — e.g. "scheduled", "played", "reported"

### 9. Team Stats (`TeamStatController`)
- CRUD on team statistics (`/api/team-stats`)
- Filter by `?division_id=`
- Stats: wins, losses, ties, winRounds, looseRounds, points

### 10. Registrations (`RegistrationController`)
- CRUD on season registrations (`/api/registrations`)
- Links teams to seasons

### 11. Match Proposals (`MatchProposalController`)
- CRUD on match date proposals (`/api/match-proposals`)
- Captains propose dates, opponent captain accepts/rejects
- Counter-proposals supported (status: pending → accepted/rejected/counter)
- Accepted proposal updates the game date, rejects other pending proposals
- Push notifications on new proposals and responses
- Filters: `?game_id=`, `?receiver_id=`, `?discord_id=`, `?status=`

### 12. Match Reports (`MatchReportController`)
- `POST /api/games/{id}/report` — captain postpones a match (uses 1 of 2 allowed per season)
- `POST /api/games/{id}/admin-report` — admin forces postponement for both teams (punishment)
- `GET /api/games/{id}/reports` — list reports for a game
- `GET /api/teams/{id}/reports?season_id=X` — team reports with count and remaining
- Reported match: date set to null, status changed to "reported"

### 13. Push Notifications (`PushSubscriptionController`)
- `GET /api/push/vapid-public-key` — public VAPID key for frontend
- `POST /api/push/subscribe` — subscribe to push notifications (ROLE_USER)
- `DELETE /api/push/subscribe` — unsubscribe
- Automatic cleanup of expired subscriptions on send failure

### 14. Match Reminders (Scheduler)
- Hourly cron via Symfony Scheduler + Messenger
- Sends push notifications to all team members 24h before a game
- Tracks `game.reminder_sent_at` to avoid duplicate reminders

### 15. Logging & Email Alerts
- Monolog with rotating file handlers (INFO 14 days, ERROR 30 days)
- Email alerts on ERROR/CRITICAL via `fingers_crossed` → `deduplication` → `symfony_mailer`
- 404/405 excluded from email alerts
- Test command: `php bin/console app:test-email-alert`
- Env: `MAILER_DSN`, `MAILER_FROM`, `MAILER_TO`

## Conventions

- API prefix: all routes start with `/api/`
- Controllers return JSON; entity serialization via `formatEntityData()` per controller
- Query parameter expansion: some endpoints support `?expand=players,stats` for nested data
- Rate limiting on auth endpoints via `config/packages/rate_limiter.yaml`
- Logging via Monolog (`config/packages/monolog.yaml`), app logs use `app` channel
- Email alerts in prod: handlers chain `mail_buffer → mail_dedup → mail_sender`
- Push notification calls always wrapped in try/catch to avoid blocking main logic
- Env: `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` for Web Push
