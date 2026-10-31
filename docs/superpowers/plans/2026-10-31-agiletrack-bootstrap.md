# AgileTrack Bootstrap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Scaffold a maintainable Laravel application that real small teams can use for project, backlog, sprint/Kanban, dashboard, and sprint review workflows.

**Architecture:** Use Laravel's official React starter kit as a modular monolith with Inertia, React, TypeScript, Vite, Tailwind, and shadcn/ui. Use PostgreSQL for persistent relational data, Laravel policies for authorization, and server-side validation; no separate API, SSR process, or extra form-state library.

**Tech Stack:** Laravel 13 / compatible PHP (confirm installer support), official Laravel React starter kit (React 19, Inertia, TypeScript, Tailwind 4, shadcn/ui), PostgreSQL, PHPUnit/Pest as provided by starter, TypeScript checks, Vite.

**Spec:** `docs/superpowers/specs/2026-10-31-agiletrack-design.md`

## Global Constraints

- Use a Laravel monolith; do not add microservices, Kubernetes, event bus, multi-tenancy, or a separate SPA/API.
- Use React + TypeScript, Inertia.js, Vite, Tailwind CSS, and shadcn/ui components distributed into the project source.
- Use Inertia `Form`/`useForm` with shadcn fields; do not add another form-state library.
- Use PostgreSQL for the primary database; local Docker Compose may be optional and must have documented alternatives if unavailable.
- Use Laravel session authentication and password hashing; public registration must be disabled server-side and references to disabled route helpers removed from the UI.
- Check authorization on every protected read and write; UI visibility alone is not authorization.
- Do not enable customer access until the project access matrix is reviewed; internal authorized users can record reviews in the first usable slice.
- Do not invent progress, velocity, benefits, or performance formulas not established in the SRS.
- Keep secrets out of git; demo seed credentials must only be local development data and clearly documented.
- All remaining SRS requirements stay tracked for future iterations and final acceptance.

## Review Focus

- A member without access to a project cannot view its records or mutate its work; test cross-project read and write denial.
- A sprint end date earlier than its start date is rejected with a useful validation error; test both dates.
- Invalid or missing project/backlog fields do not persist partial records; test server validation and database state.
- Closing a review as approved or needs-changes records the actor/time; needs-changes has a backlog follow-up; test both outcomes.
- Disabling public registration removes both server routes and frontend generated route references; test route absence and production asset build.

---

## File Structure

The official Laravel starter kit owns standard framework and frontend files. Keep domain code in standard Laravel locations:

- `routes/web.php` - named routes, authentication middleware, and resource route groups.
- `app/Models/` - User, Project, ProjectMembership, BacklogItem, Sprint, Review, Comment, and status-history models.
- `app/Policies/` - project-scoped and role-aware authorization policies.
- `app/Http/Controllers/` - thin controllers for Projects, BacklogItems, Sprints, Reviews, and Dashboard.
- `app/Http/Requests/` - validation for create/update operations.
- `database/migrations/`, `database/factories/`, `database/seeders/` - relational schema and local-only demo records.
- `resources/js/pages/` - Inertia pages for dashboard, project index/show, backlog, sprint board, review, and account.
- `resources/js/components/ui/` - shadcn/ui source components used by pages.
- `resources/js/components/` - small domain components, not generic framework abstractions.
- `resources/js/layouts/` - official starter-kit authenticated and guest layouts.
- `tests/Feature/` - HTTP workflow, validation, and authorization tests.
- `tests/Unit/` - only domain calculations/rules that are clearer as isolated unit tests.
- `README.md` - prerequisites, setup, test commands, supported workflows, current gaps, and deployment/backup caveats.

## Task 1: Scaffold Laravel React Starter

**Files:**
- Create: Laravel application files at repository root using official installer React starter kit.
- Modify: `.gitignore` only as needed to preserve Laravel defaults and exclude secrets/build artifacts.
- Modify: `README.md` with requirements and scaffold setup instructions.

**Interfaces:**
- Produces standard Laravel React starter kit with Laravel, Inertia, React 19, TypeScript, Tailwind 4, shadcn/ui, Vite, and tests.
- Use Laravel 13 if the currently installed Laravel installer supports it and current PHP is compatible; otherwise record the exact incompatibility and use the newest supported stable Laravel version without manually fabricating starter-kit files.

- [ ] **Step 1: Check current toolchain and installer choices**

Run: `php -v && composer -V && node -v && npm -v && laravel --version && laravel new --help`
Expected: record PHP/Node versions and the supported framework/starter kit options; confirm Laravel React starter availability.

- [ ] **Step 2: Generate application in repository root**

Run the official Laravel installer interactively or with its documented flags; choose React starter kit, built-in Laravel authentication (not WorkOS), no team support unless required by defaults, and the available unit test framework. Do not overwrite user files blindly; preserve `AGENTS.md` and existing project docs.

- [ ] **Step 3: Install frontend dependencies and build**

Run: `npm install && npm run build`
Expected: Vite completes production build successfully.

- [ ] **Step 4: Run starter application tests**

Run: `php artisan test`
Expected: starter tests pass on a clean development database.

- [ ] **Step 5: Inspect generated starter and document setup**

Verify `resources/js/components/ui/` includes shadcn components, `components.json` exists, and starter-kit supports `npm run types` or its equivalent TypeScript check. Record actual clean-checkout commands in README.

- [ ] **Step 6: Commit scaffold**

```bash
git add -A
git commit -s -m "build: scaffold Laravel React application"
```

## Task 2: Configure Environment, Database, and Secure Authentication Defaults

**Files:**
- Modify: `.env.example`, `config/fortify.php` or starter-kit auth feature config, frontend auth pages and generated route usage.
- Create: `docker-compose.yml` only if database service can be kept minimal and optional.
- Modify: `README.md` with PostgreSQL setup, migration, seed, build, test, and dev-server commands.
- Test: `tests/Feature/Auth/RegistrationDisabledTest.php` or equivalent starter-compatible test.

**Interfaces:**
- Consumes: Laravel React starter kit from Task 1.
- Produces: PostgreSQL-compatible clean install; public registration is unavailable; login/logout remain available; no frontend imports call removed registration routes.

- [ ] **Step 1: Write tests for registration disabled**

Test that `GET /register` is not found and `POST /register` does not create users. Keep login route available. Add a test assertion or build check for frontend route imports as practical for the generated Wayfinder setup.

- [ ] **Step 2: Run tests and verify expected failure**

Run: `php artisan test --filter=RegistrationDisabledTest`
Expected: FAIL before registration is disabled.

- [ ] **Step 3: Disable registration in backend and UI**

Remove Fortify registration feature and remove registration page links, page imports, and generated Wayfinder references. Retain password reset only if local mail handling is documented; otherwise avoid adding email-service dependency and keep password recovery as an explicit setup limitation.

- [ ] **Step 4: Configure database and environment example**

Use PostgreSQL as primary DB settings in `.env.example`; add an optional minimal PostgreSQL Compose service if useful. Ensure `.env` is ignored and no real secrets or fixed production credentials are present.

- [ ] **Step 5: Run auth tests and production frontend checks**

Run: `php artisan test --filter=RegistrationDisabledTest && npm run types && npm run build`
Expected: test, TypeScript check, and Vite production build pass. Use the exact type-check script generated by the starter kit.

- [ ] **Step 6: Commit secure baseline**

```bash
git add -A
git commit -s -m "auth: disable public account registration"
```

## Task 3: Add Administrative Account Lifecycle and Roles

**Files:**
- Create: `app/Enums/UserRole.php` or a Laravel-compatible role value object with the eight SRS roles.
- Create: `app/Http/Controllers/Admin/UserController.php`, `app/Http/Requests/StoreUserRequest.php`, `UpdateUserRequest.php`, and an admin authorization policy/middleware.
- Modify: `app/Models/User.php`, user migration only if required for active status/role relation.
- Create: `resources/js/pages/admin/users/Index.tsx`, `Create.tsx`, `Edit.tsx`.
- Add: `tests/Feature/Admin/UserManagementTest.php`.

**Interfaces:**
- Supports listing, creating, updating, deactivating/reactivating accounts, and assigning one or more SRS roles; no public registration.
- SRS roles: administrator, Owner/Sponsor, Product Owner, Project Manager, Business Analyst, developer/team member, QA Engineer, customer/client. Store assigned roles in a normalized `user_roles` pivot; users may hold multiple roles.
- Only administrator can manage accounts in this initial policy. Prevent deactivation of the last active administrator; ensure inactive accounts cannot authenticate.
- Project membership is a separate relation that scopes project visibility; an assigned user role alone does not grant access to every project. Account creation uses an admin-entered password with framework hashing; never email credentials until email delivery is configured.

- [ ] **Step 1: Write account lifecycle and authorization tests**

Test admin can create, update, deactivate, reactivate, and assign multiple roles; non-admin receives 403; inactive user cannot log in; final admin cannot deactivate self and leave system without administrator.

- [ ] **Step 2: Run account tests to verify failure**

Run: `php artisan test --filter=UserManagement`
Expected: FAIL before account management exists.

- [ ] **Step 3: Implement role representation, policy, requests, and controller**

Prefer a small role enum and JSON or normalized role relation based on the starter schema; use a normalized relation if it keeps constraints/querying clear. Enforce authorization on every operation and hashed credentials.

- [ ] **Step 4: Implement account UI with shadcn components**

Use Table, Dialog, Form fields, Select/Combobox, Badge, and Alert Dialog for deactivation. Submit with Inertia form helpers and render validation/authorization feedback accessibly.

- [ ] **Step 5: Run account tests and frontend checks**

Run: `php artisan test --filter=UserManagement && npm run types && npm run build`
Expected: pass.

- [ ] **Step 6: Commit account lifecycle**

```bash
git add app routes database resources tests
git commit -s -m "feat: add administrative account management"
```

## Task 4: Add Project and Membership Domain with Authorization

**Files:**
- Create: `app/Models/Project.php`, `app/Models/ProjectMembership.php`, `app/Policies/ProjectPolicy.php`.
- Create: project and membership migrations/factories.
- Create: `app/Http/Requests/StoreProjectRequest.php`, `UpdateProjectRequest.php`.
- Create: `app/Http/Controllers/ProjectController.php`.
- Modify: `routes/web.php`, `app/Providers/AppServiceProvider.php` or policy auto-discovery setup.
- Create: `resources/js/pages/projects/Index.tsx`, `Create.tsx`, `Show.tsx`, and small project form component if reused.
- Add: `tests/Feature/Projects/ProjectAccessTest.php`, `ProjectWorkflowTest.php`.

**Interfaces:**
- Produces: `ProjectPolicy::viewAny(User $user): bool`, `view(User $user, Project $project): bool`, `create(User $user): bool`, `update(User $user, Project $project): bool`.
- Project membership associates user to project and contains a simple project role (manager/member/viewer as needed); global SRS roles are stored separately from project membership. Do not add a generic permission engine.
- Project fields: `name` (required, max 255), `description` (nullable text), `starts_at`/`ends_at` (nullable dates, end not before start), `status` (planned/active/on_hold/completed as initial proposal), owner/creator, timestamps.

- [ ] **Step 1: Write feature tests for project CRUD and cross-project isolation**

Cover authorized project creation/update/list/show and reject a non-member trying to view/update another project. Include validation for required name and invalid date order.

- [ ] **Step 2: Run project tests to verify failure**

Run: `php artisan test --filter=Project`
Expected: FAIL because project routes/models do not exist.

- [ ] **Step 3: Implement migrations, models, policy, requests, and controller**

Use foreign keys and indexes; scope list queries to projects user may access. Ensure policy is called for show/update; use server-side validation and safe mass assignment.

- [ ] **Step 4: Add Inertia pages using shadcn components**

Build project list, create/edit form, and detail shell with shadcn Button, Card, Input, Textarea, Label, Badge, and Table components as appropriate. Submit with Inertia `useForm`; display Laravel validation errors accessibly.

- [ ] **Step 5: Run project feature tests and frontend checks**

Run: `php artisan test --filter=Project && npm run types && npm run build`
Expected: all project tests and frontend checks pass.

- [ ] **Step 6: Commit project slice**

```bash
git add app routes database resources tests
git commit -s -m "feat: add project management and access policies"
```

## Task 5: Add Backlog Items and Assignees

**Files:**
- Create: `app/Models/BacklogItem.php`, migrations, factory, requests, controller, policy.
- Modify: `app/Models/Project.php`, project show/page navigation, `routes/web.php`.
- Create: `resources/js/pages/backlog/Index.tsx`, `Create.tsx`, `Edit.tsx`, `resources/js/components/backlog/BacklogItemForm.tsx`.
- Add: `tests/Feature/Backlog/BacklogWorkflowTest.php`, `BacklogAccessTest.php`.

**Interfaces:**
- Backlog item fields: project, title (required max 255), description nullable, priority (low/medium/high/urgent proposed), status (todo/in_progress/done proposed), assignee membership(s), optional sprint relation in later task, timestamps, archived_at nullable.
- Use a many-to-many assignment pivot from the start to satisfy the SRS open question with a small relational design; UI may select one or more project members. Product Owner can simplify to one assignee later without schema rewrite.
- `BacklogItemPolicy` verifies project access and mutation role; team members may update permitted work status, while project managers/product owners can create/edit/archive.

- [ ] **Step 1: Write workflow and authorization tests**

Test create/list/update/archive item, reject missing title/invalid priority, ensure item cannot be moved/read across unauthorized projects, and ensure assigned users belong to the project.

- [ ] **Step 2: Run backlog tests to verify failure**

Run: `php artisan test --filter=Backlog`
Expected: FAIL before implementation.

- [ ] **Step 3: Implement backlog persistence and policies**

Add foreign keys, project scope, assignee pivot, soft archive timestamp, requests, controller and policy. Avoid hard delete for items referenced by sprint/review.

- [ ] **Step 4: Implement backlog UI with shadcn**

Use shadcn Table, Badge, Dialog, Input, Textarea, Select/Combobox, Button, and Empty state as needed. Use Inertia `useForm`; do not add React Hook Form or another form library.

- [ ] **Step 5: Run backlog tests and frontend checks**

Run: `php artisan test --filter=Backlog && npm run types && npm run build`
Expected: pass.

- [ ] **Step 6: Commit backlog slice**

```bash
git add app routes database resources tests
git commit -s -m "feat: add project backlog management"
```

## Task 6: Add Sprints, Kanban, and Status History

**Files:**
- Create: `app/Models/Sprint.php`, `app/Models/BacklogStatusHistory.php`, migrations, factory, requests, controller, policy.
- Modify: `app/Models/BacklogItem.php`, `Project.php`, `routes/web.php`.
- Create: `resources/js/pages/sprints/Index.tsx`, `Show.tsx`, `resources/js/components/sprints/KanbanBoard.tsx`, `KanbanColumn.tsx`, `WorkItemCard.tsx`.
- Add: `tests/Feature/Sprints/SprintWorkflowTest.php`, `KanbanWorkflowTest.php`.

**Interfaces:**
- Sprint fields: project, name (required), goal nullable, starts_at, ends_at, status (planned/active/closed), timestamps. End date must not precede start.
- Status values: `todo`, `in_progress`, `done`. Every successful status change creates history with previous status, next status, actor, and timestamp in the same database transaction.
- Dashboard progress is status counts; do not convert to percentage or story-point velocity.

- [ ] **Step 1: Write sprint and Kanban tests**

Test sprint creation/date validation, adding/removing project backlog items, assignment display, valid status updates and status history, rejection of unauthorized/invalid transition, and project-level isolation.

- [ ] **Step 2: Run tests to verify failure**

Run: `php artisan test --filter=Sprint && php artisan test --filter=Kanban`
Expected: FAIL before implementation.

- [ ] **Step 3: Implement sprint relation, policies, and transactional status history**

Use a pivot for sprint items; enforce project consistency in validation. Keep transition rules minimal: todo/in_progress/done transitions are allowed to authorized project members, with completed sprint restrictions explicit in tests.

- [ ] **Step 4: Implement sprint and Kanban pages**

Use shadcn Card, Badge, Button, Select, Tabs, and responsive columns. Provide accessible status controls; avoid drag-and-drop dependency in the first usable slice unless needed after usability feedback.

- [ ] **Step 5: Run sprint/Kanban and frontend checks**

Run: `php artisan test --filter=Sprint && php artisan test --filter=Kanban && npm run types && npm run build`
Expected: pass.

- [ ] **Step 6: Commit sprint/Kanban slice**

```bash
git add app routes database resources tests
git commit -s -m "feat: add sprint planning and kanban board"
```

## Task 7: Add Dashboard and Sprint Review Workflow

**Files:**
- Create: `app/Models/SprintReview.php`, `app/Models/ReviewDecision.php` or a compact decision schema on SprintReview, migration, requests, controller, policy.
- Create: `app/Http/Controllers/DashboardController.php`.
- Modify: `routes/web.php`, project and sprint relations.
- Create: `resources/js/pages/Dashboard.tsx`, `resources/js/pages/reviews/Show.tsx`, `resources/js/pages/reviews/Form.tsx`.
- Add: `tests/Feature/Dashboard/DashboardSummaryTest.php`, `tests/Feature/Reviews/ReviewWorkflowTest.php`.

**Interfaces:**
- Dashboard props: `projects` summaries and counts `{todo, inProgress, done}`, active sprint labels, upcoming review date where available; no unapproved aggregate formula.
- Review fields: project, sprint, scheduled_at nullable, notes nullable, feedback nullable, decision (`pending`, `approved`, `needs_changes`), decided_by nullable, decided_at nullable.
- `needs_changes` records follow-up through a linked/new backlog item. Internal authorized roles can record review; no external customer account flow yet.

- [ ] **Step 1: Write dashboard and review behavior tests**

Test dashboard shows only accessible projects and accurate status counts. Test review create/update, decision actor/time, unauthorized decision rejection, and required backlog follow-up for `needs_changes`.

- [ ] **Step 2: Run tests to verify failure**

Run: `php artisan test --filter=Dashboard && php artisan test --filter=Review`
Expected: FAIL before implementation.

- [ ] **Step 3: Implement dashboard queries and review model/controller/policy**

Query from source records without denormalized counters. Save decision and actor/time atomically; link a follow-up backlog item when changes are requested.

- [ ] **Step 4: Implement accessible UI using shadcn components**

Use Cards for status counts, Table/Badge for projects, Calendar/Date Picker if already present in starter (otherwise native date input styled through shadcn Input), and Dialog/Form fields for review decision. Use Inertia forms and inline validation errors.

- [ ] **Step 5: Run dashboard/review, full backend, and frontend checks**

Run: `php artisan test && npm run types && npm run build`
Expected: all tests and builds pass.

- [ ] **Step 6: Commit dashboard/review slice**

```bash
git add app routes database resources tests
git commit -s -m "feat: add project dashboard and sprint reviews"
```

## Task 8: Seed Data, Documentation, and Clean-Checkout Verification

**Files:**
- Create/modify: `database/seeders/DatabaseSeeder.php`, factories/seeders for local demo records.
- Modify: `README.md`.
- Create: `docs/requirements-traceability.md` mapping current features/tests to SRS FR/NFR and deferred items.
- Modify: `.env.example`, Compose file if used.

**Interfaces:**
- `php artisan db:seed` provides local demo users/projects only and is explicitly development-only; production seeding does not create default credentials.
- Documentation explains environment setup, PostgreSQL, migration/seed, tests, type check, Vite build, local server, feature status and limitations.

- [ ] **Step 1: Add traceability matrix**

Map implemented project/backlog/sprint/Kanban/dashboard/review workflows to FR IDs and test names. Explicitly list benefits, delivery history, reports/export, notifications, CSV, operations, bilingual UI, cloud deployment, and backup/recovery as deferred/not yet implemented.

- [ ] **Step 2: Add development-only seed data**

Seed a small example project, two sprints/items, memberships, and account credentials loaded from development-only environment variables or loudly documented local-only seed constants. Use hashed passwords; make production seeding fail or remain inert for demo records.

- [ ] **Step 3: Verify clean setup**

Run from a clean database with documented commands: dependency install, environment setup, key generation, migrate, seed, tests, TypeScript check, production asset build. Confirm no `.env`, secrets, or compiled dependency directories are tracked.

- [ ] **Step 4: Run full verification and review SRS coverage**

Run: `php artisan test && npm run types && npm run build && php artisan route:list`
Expected: all checks pass; registration routes absent; project routes protected; current/deferred FR/NFR status documented.

- [ ] **Step 5: Commit bootstrap documentation and seed**

```bash
git add -A
git commit -s -m "docs: document AgileTrack setup and SRS coverage"
```

## Self-Review: Spec Coverage and Gaps

- FR-01 authentication: Tasks 1-2.
- FR-02 account lifecycle/roles: Task 3; scoped to admin account CRUD and assignment of SRS roles.
- FR-03 projects: Task 4.
- FR-04/05 backlog and priority: Task 5.
- FR-06/07/08 sprint, Kanban, assignment: Task 6.
- FR-09 progress: Task 6-7 status counts only; percentage formula deferred until PO validation.
- FR-10/11 burndown/velocity: deferred pending estimates/definitions.
- FR-12 release/increment history: deferred, must be tracked.
- FR-13/14/15 review, feedback, decision: Task 7, internal-only for first slice.
- FR-16/17/18 benefits/KPI: deferred and tracked.
- FR-19 dashboard: Task 7.
- FR-20/21 reports/export: deferred pending scope/format clarification.
- FR-22/23 notifications: deferred pending event/provider decisions.
- FR-24 comments: review feedback included; general item comments deferred.
- FR-25 CSV: deferred; optional scope.
- FR-26 config: framework environment config only; user-facing admin configuration deferred.
- FR-27 activity log: status/decision history only for current flows; broader audit trail deferred.
- FR-28 and NFR-06 backup/recovery: operational requirement remains explicitly deferred to deployment/operations plan; not satisfied by local scaffold.
- NFR-01/03 access privacy: Tasks 3-7 tests.
- NFR-02 credentials: Task 2 framework hash; inspect storage and UI masking.
- NFR-04 performance: target unresolved; avoid claims, validate later before UAT.
- NFR-05 99% monthly availability: depends on provider agreement; deployment plan required.
- NFR-07/08 usability/responsiveness: shadcn accessible components and manual desktop/small screen checks in Task 8.
- NFR-09 browser compatibility: target list remains PO decision.
- NFR-10 bilingual: implementation remains explicitly deferred, not met by translation scaffolding alone.
- NFR-11 maintainability: Laravel conventions, starter patterns, README.
- NFR-12 traceability: Task 8 traceability matrix.
- NFR-13 data integrity: constraints/validation in Tasks 4-7.
- NFR-14 incident recording: operations procedure remains deferred.

Keep this plan scoped to one bootstrap release, then prepare separate follow-on plans for benefits/operations/reporting after PO rules are validated.