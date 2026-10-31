# AgileTrack

Small agile project-management web app for a student team. Laravel 13 +
Inertia 3 + React 19 + TypeScript + Tailwind 4, with Fortify authentication.

## Prerequisites

- PHP 8.3 or newer (8.5 tested), with the `pdo_sqlite` extension for local
  development and `pdo_pgsql` for deployment
- Composer 2
- Node.js 26 and npm

## Install

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan db:seed
npm install
npm run build
```

`php artisan db:seed` creates the local demo administrator
`test@example.com` / `password` from
`database/seeders/DatabaseSeeder.php`. That account is for development only,
must never be seeded in production (the seeder returns early when
`APP_ENV=production`), and is the only way to reach the user management pages
at `/admin/users`: there is no public registration, so administrators create
every other account from there.

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

Public registration is disabled, so nobody can create an account from the
login page. The first administrator comes from `php artisan db:seed` (local
demo account, development only). That administrator manages every other
account, its roles and its access status from `/admin/users`; accounts are
created with an administrator-entered password and no email is sent.
Login, logout, password reset, email verification and two-factor
authentication remain available. An inactive account cannot sign in, and an
administrator cannot deactivate the last active administrator.

Password reset email in development goes to the log
(`MAIL_MAILER=log`); check `storage/logs/laravel.log` for the reset link.
