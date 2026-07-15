# idomarketing

## Overview

`idomarketing` is a web application built on the **Laravel React Starter Kit**: a Laravel 13 backend serving an Inertia v3 single-page app with a React 19 + TypeScript frontend. There is no separate REST API — server routes return `Inertia::render('page')` and the matching React component is rendered client-side. The codebase currently contains only the starter foundation (authentication, account settings, dashboard); product-specific features are not yet built.

## Current Features (Foundation)

- **Authentication** (via Laravel Fortify): login, registration, password reset, email verification, password confirmation.
- **Two-factor authentication** (TOTP + recovery codes) and **passkeys** (WebAuthn, via `@laravel/passkeys` / `laravel/pao`).
- **Account settings**: profile update/delete, password change, appearance (light/dark).
- **Dashboard** shell with sidebar/header app layouts.
- Rate limiting for login, two-factor, and passkey flows.

## Tech Stack

- **Programming language:** PHP 8.4, TypeScript
- **Backend framework:** Laravel 13
- **Frontend:** React 19 via Inertia.js v3 (SPA), Vite 8, Tailwind CSS v4 (React Compiler enabled)
- **Auth:** Laravel Fortify (+ 2FA, passkeys)
- **Routing bridge:** Laravel Wayfinder (typed TS route/action helpers, generated)
- **Database:** MySQL 8 (local via Herd)
- **ORM:** Eloquent
- **Cache / session / queue:** Redis (queues supervised by Laravel Horizon)
- **Admin panel:** Filament v5 (`/admin`)
- **Telegram:** defstudio/telegraph v1
- **Phone numbers:** propaganistas/laravel-phone v6
- **Testing:** Pest 4 (Feature + Unit)
- **Tooling:** Pint (format), Larastan/PHPStan (static analysis), ESLint + Prettier (frontend), Laravel Pail (logs)
- **Local environment:** Laravel Herd — served at `https://idomarketing.test`

## Architecture Notes

- **Auth is Fortify-driven:** login/register/password/2FA/verification routes and controllers are registered by Fortify (not in `routes/`). `app/Providers/FortifyServiceProvider.php` is the wiring hub — it maps each Fortify view to an Inertia page, points user creation/password reset at custom actions in `app/Actions/Fortify/`, and defines rate limiters.
- **Routes** split between `routes/web.php` (home, gated dashboard) and `routes/settings.php` (profile/security/appearance, with `RequirePassword` gating the security page).
- **Shared validation** lives in `app/Concerns/` traits, consumed by both Fortify actions and `app/Http/Requests/Settings/*` form requests.
- **Inertia shared props** (`name`, `auth.user`, `sidebarOpen`) are injected by `app/Http/Middleware/HandleInertiaRequests.php`; appearance handled by `HandleAppearance`.
- **Wayfinder** generates `resources/js/routes/` and `resources/js/actions/` from PHP routes/controllers (Vite plugin in dev + `wayfinder:generate`). These are generated artifacts — never hand-edited.
- **Frontend layers:** `pages/` (map to `Inertia::render()` strings) → `layouts/` (swappable app/auth/settings shells) → `components/ui/` (Radix + `class-variance-authority` + `tailwind-merge` primitives).

## Architecture

See `.ai-factory/ARCHITECTURE.md` for detailed architecture guidelines, folder structure, dependency rules, and code examples.
**Pattern:** Structured Modules (Technical Layers)

## Non-Functional Requirements

- **Logging:** Laravel logging (`config/logging.php`); tail with `php artisan pail`.
- **Error handling:** structured validation via Form Requests; Inertia error pages / `httpException` handling on the client.
- **Security:** rate limiting on auth flows; passkey + 2FA support; password confirmation gate on sensitive settings; keep secrets in `.env` (never committed).
- **Testing:** every change covered by Pest tests; CI gate via `composer run ci:check` (Pint + Prettier + ESLint + PHPStan + tests).
