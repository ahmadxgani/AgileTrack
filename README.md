# AgileTrack

Small agile project-management web app for a student team. Laravel 13 +
Inertia 3 + React 19 + TypeScript + Tailwind 4, with Fortify authentication.

## Prerequisites

- PHP 8.5 with the `pdo_sqlite` extension (and `pdo_pgsql` for deployment)
- Composer 2
- Node.js 26 and npm

## Install

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
npm run build
```

## Development

```bash
composer run dev
```

Serves the app at http://localhost:8000 with the Vite dev server attached.

## Checks

```bash
php artisan test            # backend test suite
npm run types:check         # TypeScript
npm run check               # lint and format check
composer ci:check           # lint, type check, tests
```

## Database

Local development uses SQLite (`DB_CONNECTION=sqlite`,
`database/database.sqlite`), because `pdo_pgsql` is not installed on the
development machine. Migrations must stay portable: no PostgreSQL-only column
types and no driver-specific raw SQL.

Deployment must use PostgreSQL. Install and enable the `pdo_pgsql` PHP
extension, create the database, and uncomment the PostgreSQL block in
`.env.example`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=agiletrack
DB_USERNAME=agiletrack
DB_PASSWORD=change-me
```

Then run `php artisan migrate --force`.

## Authentication

Public registration is disabled. Accounts are created by an administrator.
Login, logout, password reset, email verification and two-factor
authentication remain available.

Password reset email in development goes to the log
(`MAIL_MAILER=log`); check `storage/logs/laravel.log` for the reset link.
