[← Development](development.md) · [Back to README](../README.md) · [Security →](security.md)

# Configuration

Configuration lives in `.env` (from `.env.example`) and the `config/` directory. This page covers the settings most relevant to `idomarketing`.

## Environment Variables

Key variables from `.env.example`:

| Variable | Purpose | Notes |
|----------|---------|-------|
| `APP_NAME` | Application name | Exposed to the frontend as `VITE_APP_NAME` |
| `APP_ENV` | Environment | `local`, `production`, etc. |
| `APP_KEY` | Encryption key | Set by `php artisan key:generate` |
| `APP_DEBUG` | Debug mode | `true` locally, **`false`** in production |
| `APP_URL` | Base URL | `https://idomarketing.test` for Herd |
| `DB_CONNECTION` | Database driver | **`mysql`** |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | MySQL connection | Schema `idomarketing` |
| `CACHE_STORE` | Cache backend | **`redis`** |
| `SESSION_DRIVER` | Session storage | **`redis`** |
| `QUEUE_CONNECTION` | Queue backend | **`redis`** (supervised by Horizon) |
| `REDIS_CLIENT` / `REDIS_HOST` / `REDIS_PORT` | Redis connection | `phpredis`, `127.0.0.1:6379` |
| `MAIL_MAILER` | Mail transport | Required for password reset & email verification |
| `LOG_CHANNEL` / `LOG_LEVEL` | Logging | See [logging](#logging) |

## Database

The app uses **MySQL 8**. Create the schema and set credentials before migrating:

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS idomarketing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate
```

> The test suite runs on sqlite `:memory:` (see `phpunit.xml`), so tests are isolated from your MySQL data.

## Redis, Cache & Queues

Cache, session, and queue all use **Redis** (`phpredis` client). Make sure Redis is running (`redis-cli ping` → `PONG`).

Queues are supervised by **Laravel Horizon**:

```bash
php artisan horizon              # start the worker/supervisor
```

- Dashboard: **`/horizon`** (open in `local`; restrict for production via the `viewHorizon` gate in `app/Providers/HorizonServiceProvider.php`).
- Config: `config/horizon.php`.

## Authentication Features (Fortify)

Enabled auth features are toggled in `config/fortify.php` under `'features'`:

```php
'features' => [
    Features::registration(),
    Features::resetPasswords(),
    Features::emailVerification(),
    Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
    Features::passkeys(['confirmPassword' => true]),
],
```

See [Security](security.md) for how these map to routes and views.

## Admin Panel (Filament)

The Filament admin panel is served at **`/admin`** (`app/Providers/Filament/AdminPanelProvider.php`). Access is gated by `User::canAccessPanel()` — currently open to any authenticated user (all users are staff). Create admin users with `php artisan make:filament-user`.

## Telegram (Telegraph)

`config/telegraph.php` configures the `defstudio/telegraph` integration (bot storage, webhook handler, security). The bot handler, webhook, and FSM storage are wired up in the later bot milestones; at this stage only the package, tables (`telegraph_bots`, `telegraph_chats`), and config are in place.

## Config Files

| File | Purpose |
|------|---------|
| `config/fortify.php` | Auth features, guard, password-reset settings |
| `config/horizon.php` | Queue supervisor / dashboard settings |
| `config/telegraph.php` | Telegram bot, webhook, storage settings |
| `config/inertia.php` | Inertia + SSR settings |
| `config/database.php` | Database + Redis connections |
| `config/queue.php` | Queue connections |
| `config/mail.php` | Mail transports |

Read any value with `php artisan config:show <key>` (e.g. `php artisan config:show database.default`).

## Logging

Laravel logging is configured in `config/logging.php` and driven by `LOG_CHANNEL` / `LOG_LEVEL`. Tail logs during development:

```bash
php artisan pail
```

## See Also

- [Security](security.md) — the authentication model in depth
- [Getting Started](getting-started.md) — creating your `.env`
- [Development](development.md) — running the app
