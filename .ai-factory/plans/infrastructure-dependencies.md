# Implementation Plan: Infrastructure & Dependencies

Branch: main (no feature branch — `git.create_branches=false`)
Created: 2026-07-15

## Settings
- Testing: yes (infrastructure smoke tests)
- Logging: verbose
- Docs: yes  # mandatory docs checkpoint in /aif-implement (update getting-started.md + configuration.md via /aif-docs)

## Roadmap Linkage
Milestone: "Infrastructure & Dependencies"
Rationale: This plan is the prerequisite milestone that installs and configures the LEADBOT-001 stack before any feature work.

## Scope Decisions (confirmed with user)
- **Database:** MySQL (chosen over the ТЗ's PostgreSQL and the current SQLite). Herd provides MySQL locally.
- **Redis + queues:** Full — cache/session/queue on Redis, plus Laravel Horizon (per ТЗ).
- **Filament:** Install in this milestone (panel scaffolding only; LeadResource comes later). Coexists with Inertia/React on separate routes.
- **Also installed:** `defstudio/telegraph` v1 and `propaganistas/laravel-phone` (dependencies for later milestones; no bot/normalization logic here).
- **Note:** the `.mcp.json` `postgres` server is now mismatched (app uses MySQL) — removed in this plan.

> Out of scope (later milestones): `Lead` model/migration/enum, `LeadService` phone normalization, `LeadBotHandler` FSM, webhook/bot registration, `LeadResource` admin UI.

## Commit Plan
- **Commit 1** (after tasks 1–3): `chore: migrate to MySQL and Redis cache/queue/session + Horizon`
- **Commit 2** (after tasks 4–6): `chore: add defstudio/telegraph, laravel-phone, and Filament admin panel`
- **Commit 3** (after tasks 7–8): `chore: reconcile MCP config and add infrastructure smoke tests`

## Tasks

### Phase 1: Database & Redis
- [x] Task 1: Switch database to MySQL — `.env`/`.env.example` (DB_CONNECTION=mysql, host/port/db/user/pass), create `idomarketing` DB, run existing migrations on MySQL, drop reliance on `database/database.sqlite`. Verify with `migrate:status` + `db:show`.
- [x] Task 2: Switch cache/session/queue to Redis — start Herd Redis, set `CACHE_STORE=redis`/`SESSION_DRIVER=redis`/`QUEUE_CONNECTION=redis` + `REDIS_*` in `.env`/`.env.example`. Verify cache round-trip + `redis-cli ping`.

### Phase 2: Queue supervision
- [x] Task 3: Install and configure Laravel Horizon — `composer require laravel/horizon`, `horizon:install`, register `viewHorizon` auth gate, review dev worker process. Verify `/horizon` loads when authorized. (depends on 2)
<!-- Commit checkpoint: tasks 1–3 -->

### Phase 3: Domain dependencies
- [x] Task 4: Install and set up `defstudio/telegraph` — `composer require defstudio/telegraph`, `telegraph:install`, run package migrations (`telegraph_bots`/`telegraph_chats`), publish `config/telegraph.php`. No handler/bot yet. (depends on 1)
- [x] Task 5: Install `propaganistas/laravel-phone` — `composer require propaganistas/laravel-phone`, publish config if any. Dependency install only; normalization logic is a later milestone.
- [x] Task 6: Install Filament admin panel — `composer require filament/filament`, `filament:install --panels` (panel at `/admin`), make `User` implement `FilamentUser::canAccessPanel()`, create an admin user. Verify `/admin` loads and existing Inertia routes still work. (depends on 1)
<!-- Commit checkpoint: tasks 4–6 -->

### Phase 4: Cleanup & verification
- [x] Task 7: Reconcile MCP config — remove the mismatched `postgres` server from `.mcp.json` (keep `laravel-boost`/`github`/`filesystem`/`chromeDevtools`/`playwright`). Note `DATABASE_URL` no longer needed.
- [x] Task 8: Add infrastructure smoke tests — new `tests/Feature/Infrastructure/StackSmokeTest.php` asserting MySQL driver, Redis cache round-trip + redis queue, `/admin` access control, and that the full existing Pest suite passes on MySQL+Redis. (depends on 1,2,3,4,5,6)
<!-- Commit checkpoint: tasks 7–8 -->

## Verification (Definition of Done)
- `php artisan db:show` reports driver `mysql`; all migrations Ran.
- Redis reachable; cache/session/queue resolve to redis; `php artisan horizon:status` OK.
- `defstudio/telegraph`, `propaganistas/laravel-phone`, `filament/filament`, `laravel/horizon` present in `composer.json`; app boots.
- `/admin` panel loads for an authorized user; unauthorized users are blocked.
- `.mcp.json` has no `postgres` server and is valid JSON.
- `php artisan test --compact` is green (smoke tests + existing suites).
- Docs checkpoint: `getting-started.md` + `configuration.md` updated for the new stack.
