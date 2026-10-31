# AgileTrack requirements traceability

Status of the SRS requirements against the bootstrap release (Tasks 1-8).
This is an honest snapshot, not a goal: anything here marked NOT YET
IMPLEMENTED is absent from the application, not hidden behind a flag.

The SRS is the `.docx` named in `docs/superpowers/specs/2026-10-31-agiletrack-design.md`.
Requirement IDs below follow the numbering used in
`docs/superpowers/plans/2026-10-31-agiletrack-bootstrap.md`.

Legend: **Implemented** means the code exists and an automated test proves
it. **Partly** means a defensible subset exists and the rest is deferred.

## Functional requirements

### FR-01 Authentication

**Implemented.** Session login and logout through Fortify, rate limiting,
password reset, email verification, password confirmation and two-factor
authentication. Public registration is disabled in the backend and removed
from the UI.

- `tests/Feature/Auth/AuthenticationTest.php`
- `tests/Feature/Auth/RegistrationDisabledTest.php`
- `tests/Feature/Auth/PasswordResetTest.php`
- `tests/Feature/Auth/EmailVerificationTest.php`
- `tests/Feature/Auth/VerificationNotificationTest.php`
- `tests/Feature/Auth/PasswordConfirmationTest.php`
- `tests/Feature/Auth/TwoFactorChallengeTest.php`
- `tests/Feature/Settings/SecurityTest.php`

### FR-02 Account lifecycle and roles

**Implemented, scoped** to administrator account CRUD and assignment of the
eight SRS account roles (administrator, owner/sponsor, product owner,
project manager, business analyst, team member, QA engineer, customer).
Accounts are created with an administrator-entered password; no invitation
email exists. An inactive account cannot sign in and loses its session, and
the last active administrator cannot be deactivated, deleted or demoted.

- `tests/Feature/Admin/UserManagementTest.php`

### FR-03 Projects

**Implemented.** Create, list, view, edit and archive projects; per-project
memberships with manager / member / viewer roles; manager-managed member
add, change and remove with the last-manager guard.

- `tests/Feature/Projects/ProjectWorkflowTest.php`
- `tests/Feature/Projects/ProjectAccessTest.php`

### FR-04 Backlog and FR-05 Priority

**Implemented.** Backlog items with title, description, priority (low,
medium, high, urgent) and status (to do, in progress, done); items are
archived rather than deleted, archived items and archived projects are read
only, and assignees must be members of the same project.

- `tests/Feature/Backlog/BacklogWorkflowTest.php`
- `tests/Feature/Backlog/BacklogAccessTest.php`

### FR-06 Sprint and FR-07 Kanban

**Implemented.** Sprint planning (create, edit, plan start and end dates,
choose items from the pool), start, close and restricted deletion, one
active sprint per project, closed sprints read only, and a Kanban board that
groups the sprint items into columns with transactional status history.

- `tests/Feature/Sprints/SprintWorkflowTest.php`
- `tests/Feature/Sprints/KanbanWorkflowTest.php`

### FR-08 Assignment

**Implemented.** One or more project members per backlog item; an assigned
member may move only their own cards; removing a member clears their
assignments in that project.

- `tests/Feature/Backlog/BacklogWorkflowTest.php`
- `tests/Feature/Backlog/BacklogAccessTest.php`
- `tests/Feature/Sprints/KanbanWorkflowTest.php`

### FR-09 Progress

**Partly.** The dashboard shows live counts derived from the stored data
(items per status), the name of the running sprint and the next scheduled
review. No percentage formula is shown because the SRS does not define one and
the product owner has not validated one.

- `tests/Feature/Dashboard/DashboardSummaryTest.php`

### FR-10 Burndown chart / FR-11 Velocity

**NOT YET IMPLEMENTED.** Both charts need an estimation unit and a
calculation definition that the SRS leaves open; building them now would
lock a guess into the schema. Follow-on plan after product owner validation.

### FR-12 Release and increment history

**NOT YET IMPLEMENTED.** No release, version or increment record exists yet.
Tracked here so it is not lost; separate follow-on plan.

### FR-13 Sprint review schedule, FR-14 Feedback, FR-15 Decision

**Implemented, internal only.** A review holds a schedule, notes and
feedback; managers (not customers) record the decision approved / needs
changes / pending, stamped with the deciding user and time. A needs-changes
decision files exactly one follow-up backlog item in the same project and
reuses it. Customer-facing review access is deferred until the external
customer flow is validated.

- `tests/Feature/Reviews/ReviewWorkflowTest.php`

### FR-16 / FR-17 / FR-18 Benefits tracker, KPI, realisation

**NOT YET IMPLEMENTED.** Benefit owner, KPI, target and period rules are
product owner decisions that are still open. Deferred and tracked.

### FR-19 Dashboard

**Implemented.** The dashboard lists only projects the user may see with
per-project live counts of unarchived items, the running sprint name and the
next upcoming review, and its query count does not grow per project.

- `tests/Feature/Dashboard/DashboardSummaryTest.php`
- `tests/Feature/DashboardTest.php`

### FR-20 / FR-21 Reports and export

**NOT YET IMPLEMENTED.** FR-20 has conflicting priorities in the SRS and the
FR-21 export format is unclear; deferred pending scope and format
clarification.

### FR-22 / FR-23 Notifications

**NOT YET IMPLEMENTED.** Notification events and the delivery channel are
not decided; no mail or in-app notification is sent by the workflow code.
Deferred pending event and provider decisions.

### FR-24 Comments

**Partly.** Review feedback text is implemented as part of FR-14. General
per-item comments with authors and timestamps are deferred; the schema has
no comment entity yet.

### FR-25 CSV import

**NOT YET IMPLEMENTED.** Optional scope, deferred.

### FR-26 Configuration

**Partly.** Configuration is framework environment configuration only
(`.env` / `.env.example`); user-facing administrative configuration pages
are deferred.

### FR-27 Activity log

**Partly.** Status moves and review decisions keep history rows (actor,
from/to status, decision actor and time). A broad audit trail over every
account, project and membership change is deferred.

### FR-28 Backup and recovery, NFR-06

**NOT YET IMPLEMENTED.** The local scaffold proves nothing about production
backup; daily backup, checks and a restore rehearsal belong to the
deployment and operations plan.

## Non-functional requirements

| ID | Requirement | Status | Evidence |
| --- | --- | --- | --- |
| NFR-01 | Access control and privacy | Implemented | Policy tests across `tests/Feature/Projects`, `Backlog`, `Sprints`, `Reviews`, `Admin`; scoped route bindings (`scopeBindings()` + cross-project 404 tests) |
| NFR-02 | Credential storage | Implemented | Framework password hash (`password` is a `hashed` cast, never stored as typed); the model hides `password` and both two-factor columns from every serialized payload (`#[Hidden]`), and the account forms use a masked password input. The demo password is a documented local-only constant; `UserManagementTest` asserts the stored value is not the entered text |
| NFR-03 | Server-side authorization on every read and write | Implemented | Policies plus query scopes (`Project::visibleTo`), re-authorized on locked rows inside transactions (`KanbanWorkflowTest`, `SprintWorkflowTest`, `ReviewWorkflowTest` stale-state tests) |
| NFR-04 | Performance targets | NOT YET IMPLEMENTED | No target number exists in the SRS; no claim is made. Query-count regression tests guard against N+1 (`DashboardSummaryTest`, `KanbanWorkflowTest`) |
| NFR-05 | 99% monthly availability | NOT YET IMPLEMENTED | Depends on a hosting agreement; requires the deployment plan |
| NFR-06 | Backup and recovery | NOT YET IMPLEMENTED | See FR-28 |
| NFR-07 | Usability | Partly | shadcn/ui components, keyboard-reachable forms, inline validation messages; no formal usability test yet |
| NFR-08 | Responsiveness | Partly | Responsive layouts checked manually at desktop and small screen widths; no automated visual test |
| NFR-09 | Browser compatibility | NOT YET IMPLEMENTED | Supported browser list is still a product owner decision |
| NFR-10 | Bilingual UI | NOT YET IMPLEMENTED | English copy only; translation scaffolding alone does not satisfy it |
| NFR-11 | Maintainability | Implemented | Laravel conventions, enums in `app/Enums`, thin controllers, `composer ci:check` (Pint, PHPStan level 7, PHPUnit) and `npm run types:check`, `npm run check` |
| NFR-12 | Traceability | Implemented | This document |
| NFR-13 | Data integrity | Implemented | Foreign keys with `nullOnDelete` where a person may leave (`created_by`, `decided_by`) and `restrictOnDelete` on the sprint and review chain, unique constraints (`user_roles` on user and role, `backlog_item_user` on item and user), server-side validation, and `DB::transaction` around multi-row changes. The single active sprint per project is enforced by the sprint actions and their test, not by a database constraint |
| NFR-14 | Incident recording | NOT YET IMPLEMENTED | Operational procedure belongs to the deployment and operations plan |

## Verification evidence for this release

- `php artisan migrate:fresh --seed` on SQLite builds the documented demo.
- `tests/Feature/DatabaseSeederTest.php` proves the demo is sign-in able and
  that the seeder is inert under `APP_ENV=production`.
- `php artisan test`, `npm run types:check`, `npm run check`, `npm run build`
  and `php artisan route:list` pass on the release tree.
- Registration routes are absent from `php artisan route:list`.

## Deferred, by area

| Area | Requirement | Why deferred | Needs |
| --- | --- | --- | --- |
| Benefits tracker | FR-16, FR-17, FR-18 | KPI definitions, units, targets unknown | Product owner rules |
| Delivery history | FR-12 | No release model agreed | Follow-on plan |
| Burndown, velocity | FR-10, FR-11 | Estimation unit and formula undefined | Product owner decision |
| Reports, export | FR-20, FR-21 | Conflicting priority, format unclear | Scope clarification |
| Notifications | FR-22, FR-23 | Events and channel undecided | Event and provider decision |
| Item comments | FR-24 (general) | Only review feedback needed now | Later iteration |
| CSV import | FR-25 | Optional scope | Priority decision |
| Admin configuration UI | FR-26 (user-facing) | Environment config suffices now | Later iteration |
| Broad audit log | FR-27 (beyond status and decision history) | Only workflow history needed now | Later iteration |
| Backup and recovery | FR-28, NFR-06 | Operational, not application code | Deployment plan |
| Availability target | NFR-05 | Needs hosting agreement | Provider agreement |
| Performance target | NFR-04 | No number in the SRS | Product owner target |
| Browser list | NFR-09 | Not decided | Product owner decision |
| Indonesian UI | NFR-10 | Not started | Translation pass |
| Incident recording | NFR-14 | Operational procedure | Operations plan |
