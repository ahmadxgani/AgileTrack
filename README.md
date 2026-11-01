# AgileTrack

Small agile project-management web app for a student team. Laravel 13 +
Inertia 3 + React 19 + TypeScript + Tailwind 4, with Fortify authentication.

## Prerequisites

- PHP 8.3 or newer (8.5 tested), with the `pdo_sqlite` extension for local
  development and `pdo_pgsql` for deployment
- Composer 2
- Node.js 26 and npm

## Install

From a clean checkout, on SQLite (the default in `.env.example`):

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed   # fresh checkout only; drops all tables
npm install
npm run build
php artisan serve        # http://127.0.0.1:8000
```

For front-end work, run `composer run dev` instead of the serve and build
steps; it runs the PHP server, the queue listener, the log viewer and the
Vite dev server together.

`php artisan migrate:fresh --seed` (or `php artisan db:seed` on an
already-migrated database) fills a local demo from
`database/seeders/DatabaseSeeder.php`. Every demo account uses the password
`password` (`DatabaseSeeder::DEMO_PASSWORD`, a development-only constant). The
seeder returns immediately when `APP_ENV=production`, so no demo account or
demo record ever reaches a production database.

| Account                 | Roles            | Project role               |
| ----------------------- | ---------------- | -------------------------- |
| `test@example.com`      | Administrator    | none (sees every project)  |
| `manager@example.com`   | Project Manager  | manager of AgileTrack Demo |
| `developer@example.com` | Team Member      | member                     |
| `qa@example.com`        | QA Engineer      | member                     |
| `analyst@example.com`   | Business Analyst | member                     |
| `owner@example.com`     | Owner / Sponsor  | viewer                     |
| `customer@example.com`  | Customer         | viewer                     |

The demo project "AgileTrack Demo" holds a closed sprint of finished work, an
active sprint with cards in every Kanban column, an approved sprint review and
a needs-changes review. Three items sit unplanned in the backlog pool: two
seeded directly and the follow-up item the needs-changes review files.

There is no public registration, so administrators create every other account
from `/admin/users`; the seeded administrator is the one way in on a fresh
clone.

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
npm run build               # production asset build
composer ci:check           # lint, type check, tests
```

## Feature status

The implemented workflows are sign in, account administration, projects and
memberships, backlog items with priorities and assignees, sprints and the
Kanban board, sprint reviews with decisions and follow-up items, and the
dashboard summary. Every one of them is covered by tests; see
[`docs/requirements-traceability.md`](docs/requirements-traceability.md) for
the FR/NFR mapping and the test files that prove each item.

Not implemented yet, and deliberately not shown as fake buttons in the UI:

- Benefits tracker, KPI and realisation (FR-16 to FR-18)
- Burndown and velocity charts (FR-10, FR-11), release history (FR-12)
- Reports and export (FR-20, FR-21), CSV import (FR-25)
- Notifications (FR-22, FR-23), general item comments (FR-24)
- User-facing configuration screens (FR-26), broad audit log (FR-27)
- Backup, recovery, availability target, performance target, incident
  recording (FR-28, NFR-04 to NFR-06, NFR-14)
- Indonesian UI (NFR-10) and a supported browser list (NFR-09)

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

### Existing EC2 deployment

The production host is already provisioned with nginx, PHP-FPM, PostgreSQL,
a shared production `.env`, persistent storage, and a `current` release
symlink. Deploy a clean, committed revision from the repository root:

```bash
scripts/deploy-ec2.sh
```

The script runs `composer ci:check`, builds the frontend, packages committed
source plus the generated assets, and uploads the release over SSH using
`~/.ssh/yuki`. On EC2 it snapshots PostgreSQL, installs locked production
dependencies, runs additive migrations (never `migrate:fresh` or seeds),
activates the release, and checks `/up` and `/login`. A failed health check
restores the previous symlink. Database migrations are not automatically
reversed; inspect and restore deliberately if a migration needs recovery.

For local checks without connecting to EC2:

```bash
DEPLOY_DRY_RUN=1 scripts/deploy-ec2.sh
```

The worktree must be clean for both deploy and dry-run. Do not commit
`.env`, database files, or local secrets. The initial production
administrator credential is not re-created by deploys. The script defaults
to the provisioned host and `/var/www/agiletrack` layout. Explicit
`DEPLOY_HOST_OVERRIDE`, `DEPLOY_USER_OVERRIDE`, or `DEPLOY_KEY_OVERRIDE`
variables can target a compatible host whose SSH host key is already
trusted in `~/.ssh/known_hosts`. Set `DEPLOY_RUN_CHECKS=0` only if CI checks already ran for the
exact commit. The script keeps the latest five releases and seven database
snapshots by default; tune with
`DEPLOY_KEEP_RELEASES` and `DEPLOY_KEEP_DB_BACKUPS`.

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

## Repository hygiene

`.env`, `vendor/`, `node_modules/`, `public/build/`, `storage/*.key` and the
SQLite file are ignored, so no secret and no build artifact is tracked. Only
`.env.example` is committed, and it holds placeholders, not values.
