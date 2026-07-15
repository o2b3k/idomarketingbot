[Back to README](../README.md) · [Architecture →](architecture.md)

# Getting Started

This guide takes you from a fresh clone to a running local instance of `idomarketing`.

## Prerequisites

| Tool | Version | Notes |
|------|---------|-------|
| PHP | 8.4 (≥ 8.3 supported) | With standard Laravel extensions + `phpredis` |
| Composer | latest | PHP dependency manager |
| Node.js + npm | LTS (Node 20+) | Frontend build via Vite |
| Laravel Herd | latest | Serves the app at `https://idomarketing.test` |
| MySQL | 8.x | App database (via Herd or Homebrew `mysql@8.0`) |
| Redis | 7.x | Cache, session, and queue backend |

## Installation

**1. Create the database** (the app expects a MySQL schema named `idomarketing`):

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS idomarketing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**2. Configure `.env`** — copy `.env.example` to `.env` and set your DB credentials:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=idomarketing
DB_USERNAME=<your-user>
DB_PASSWORD=<your-password>
```

Cache, session, and queue default to Redis (`CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`) — ensure Redis is running.

**3. Install dependencies and migrate:**

```bash
composer run setup   # install deps, generate key, migrate, build assets
```

<details>
<summary>Manual steps (equivalent)</summary>

```bash
composer install
cp .env.example .env    # then edit DB credentials
php artisan key:generate
php artisan migrate
npm install
npm run build
```

</details>

## Running the App

Start the full development environment (app server, log tailer, and Vite):

```bash
composer run dev
```

Process queued jobs with **Horizon** (Redis-backed):

```bash
php artisan horizon
```

The site is served by Laravel Herd at **https://idomarketing.test** — no separate serve command.

> If a frontend change doesn't appear, ensure Vite is running (`composer run dev` / `npm run dev`) or run `npm run build`.

## Verify It Works

1. Visit **https://idomarketing.test** — the welcome page loads.
2. Register at `/register`, sign in, and reach `/dashboard`.
3. Visit **`/admin`** (Filament) — log in with the seeded admin (`admin@idomarketing.test` / `password`; **change this**).
4. Visit **`/horizon`** — the queue dashboard loads (allowed in `local`).
5. Run the test suite:

```bash
php artisan test --compact
```

All tests should pass. (Tests run on sqlite `:memory:` per `phpunit.xml`, independent of your MySQL/Redis dev setup.)

## Next Steps

- [Architecture](architecture.md) — how the project is organized.
- [Development](development.md) — day-to-day commands.
- [Configuration](configuration.md) — environment variables, services, and feature toggles.

## See Also

- [Architecture](architecture.md) — project structure and patterns
- [Configuration](configuration.md) — services and environment variables
