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
- `parseJsonBody()` — extracts and validates JSON request bodies
- `persistEntity()` / `removeEntity()` — Doctrine persistence wrappers
- `formatResponse()` — standardized JSON responses
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

**`ApiAccessVoter`** (`src/Security/`) handles fine-grained access: `READ` for any authenticated user, `WRITE` for `ROLE_API` or `ROLE_ADMIN`.

### Error Handling

`ApiProblemException` (`src/Exception/`) provides factory methods: `notFound()`, `badRequest()`, `validationError()`, `forbidden()`, `unauthorized()`, `conflict()`, `tooManyRequests()`.

`ApiProblemExceptionListener` (`src/EventListener/`) catches exceptions on `/api` routes and returns Problem+JSON responses.

### Testing

- **Unit** (`tests/Unit/Entity/`) — entity logic and validation
- **Integration** (`tests/Integration/Repository/`) — repository queries against SQLite
- **Functional** (`tests/Functional/Controller/`) — full HTTP request/response tests

`ApiTestCase` (`tests/Functional/ApiTestCase.php`) is the base class for functional tests with helpers: `jsonRequest()`, `cleanDatabase()`, `assertJsonResponseStructure()`.

Test environment uses SQLite (configured in `.env.test`).

### Key Services

- `AuthenticationService` (`src/Service/`) — JWT creation/validation, token revocation, API key validation
- `DiscordOAuthService` (`src/Service/`) — OAuth2 flow, Discord user sync, bot secret validation

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
```

## Conventions

- API prefix: all routes start with `/api/`
- Controllers return JSON; entity serialization via `formatEntityData()` per controller
- Query parameter expansion: some endpoints support `?expand=players,stats` for nested data
- Rate limiting on auth endpoints via `config/packages/rate_limiter.yaml`
- Logging via Monolog (`config/packages/monolog.yaml`), app logs use `app` channel
- Email alerts in prod: `MAILER_DSN`, `MAILER_FROM`, `MAILER_TO` — handlers chain: `mail_buffer → mail_dedup → mail_sender` (see `config/packages/monolog.yaml`)
