# MGE-PMS — Claude Code Context

## Project
**MGE-PMS** — Construction Project Management System
**Stack:** Laravel 12 (PHP 8.2+) + React 19 + Vite 7 + Tailwind CSS 4 + MySQL
**Dev URL:** `http://mge-pms.test`

---

## Architecture

### Pattern: Controller → Service → Repository → Model
All business logic lives in Services. Controllers are thin. Repositories abstract data access.

### Backend
- **Auth:** Laravel Sanctum (stateful API, cookie-based)
- **RBAC:** Spatie Laravel-Permission (roles + permissions)
- **Real-time:** Pusher + Laravel Broadcasting
- **PDF:** barryvdh/laravel-dompdf
- **Excel:** maatwebsite/excel

### Frontend
- **State:** React Context (`AuthContext`) — stores user, token, roles, permissions
- **HTTP:** Axios via `resources/js/services/apiClient.js` (auth header injected automatically)
- **Routing:** React Router 7
- **Real-time:** Laravel Echo + Pusher (`resources/js/echo.js`)
- **Notifications:** react-hot-toast
- **Charts:** Chart.js + react-chartjs-2

---

## Key File Paths

| Area | Path |
|------|------|
| API Routes | `routes/api.php` |
| API Controllers | `app/Http/Controllers/Api/` |
| Models | `app/Models/` |
| Services | `app/Services/` |
| Repository Contracts | `app/Repositories/Contracts/` |
| Repository Implementations | `app/Repositories/Eloquent/` |
| Form Requests | `app/Http/Requests/` |
| Migrations | `database/migrations/` |
| Seeders | `database/seeders/` |
| React Entry | `resources/js/app.jsx` |
| React Pages | `resources/js/pages/` |
| React Components | `resources/js/components/` |
| React Services (API) | `resources/js/services/` |
| Auth Context | `resources/js/context/AuthContext.jsx` |
| Permission Hook | `resources/js/hooks/usePermission.js` |
| Echo Config | `resources/js/echo.js` |

---

## Roles (4)

| Role | Access |
|------|--------|
| Admin & HR | Full system access |
| Finances & HR | Finance + HR + Attendance/Payroll |
| Projects | Project management |
| Employee | Dashboard view only |

Roles seeded in: `database/seeders/RolePermissionSeeder.php`

---

## Feature Modules (14)

1. **Auth** — Login, Register (pending approval on register)
2. **Dashboard** — Role-based stats
3. **Projects** — CRUD + milestones, site logs, documents, calendar events
4. **Tasks** — CRUD + comments, attachments
5. **Clients** — Client management
6. **Users** — CRUD + approval workflow (pending → active/rejected)
7. **Finance** — Overview, invoices, expenses, monthly summary, budget vs actual
8. **Attendance & Payroll** — Upload attendance, generate/approve payroll
9. **Safety** — Incidents, hazards, toolbox meetings, compliance checklists
10. **Environmental** — Waste records, site inspections, environmental audits
11. **Chat** — Real-time private/group messaging
12. **Email** — Internal threaded email with attachments
13. **Notifications** — Real-time user notifications
14. **Roles & Permissions** — Role and permission management

---

## User Approval Workflow

Newly registered users start with `status = 'pending'`. Admin must approve.

- Status ENUM: `active | inactive | suspended | pending | rejected`
- Migration: `database/migrations/2026_02_13_000001_add_pending_rejected_to_users_status.php`
- Endpoints: `POST /api/users/{id}/approve`, `POST /api/users/{id}/reject`
- Service: `app/Services/UserService.php`
- Auth blocks login for non-active accounts: `app/Services/AuthService.php`

---

## API Route Conventions

```
GET    /api/{resource}         → index
POST   /api/{resource}         → store
GET    /api/{resource}/{id}    → show
PUT    /api/{resource}/{id}    → update
DELETE /api/{resource}/{id}    → destroy
```

Public routes: `POST /api/login`, `POST /api/register`
All others: require `auth:sanctum` middleware
Permission gates use Spatie: `$this->authorize('permission-name')`

---

## Frontend Patterns

- API calls go through `resources/js/services/{feature}Service.js`
- Auth state from `useContext(AuthContext)` → `{ user, token, roles, permissions }`
- Permission check: `usePermission()` hook or `<PermissionGate permission="..." />`
- Route protection: `<ProtectedRoute />` for auth, `<GuestRoute />` for guests
- Toast notifications: `import toast from 'react-hot-toast'`
- Loading states: `<LoadingSpinner />` component

---

## Development Commands

```bash
# Start backend
php artisan serve

# Start frontend (HMR)
npm run dev

# Build for production
npm run build

# Run migrations
php artisan migrate

# Seed roles & permissions
php artisan db:seed --class=RolePermissionSeeder

# Run all seeders
php artisan db:seed
```

---

## Database

- **Connection:** MySQL
- **31 migrations** covering all feature modules
- Soft deletes used on: User, Project (check model for `SoftDeletes` trait)
- Eager loading relations on User: `department`, `designation`, `roles`, `permissions`

---

## Notes

- CORS configured in `config/cors.php`
- Broadcasting auth: `POST /api/broadcasting/auth`
- PDF generation uses DomPDF via service classes
- Excel import classes in `app/Imports/`
- Activity logging in `ActivityLog` model
- File uploads stored in `storage/app/public/` — ensure `php artisan storage:link` is run

## Qwen sub-agent (Claude-as-lead delegation)

This project has `chillocreative/qwen-agent` installed (path-repo at `../qwen-agent`), providing a low-cost Qwen coding sub-agent for routine implementation work.

- Delegate a task: `php artisan qwen:agent storage/app/qwen-tasks/some-task.txt --context=app/Models/Foo.php,app/Http/Controllers/FooController.php`
- The command is read-only — it never writes to this project. Review Qwen's output and apply changes yourself (as Claude/lead engineer).
- ⚠️ **Do not remove the `repositories` block from `composer.json`.** The package name
  `chillocreative/qwen-agent` is not registered on Packagist, so that entry is what
  keeps Composer resolving it from our own repo. Without it, anyone who claims that
  name on Packagist could have their code installed by a future `composer update`.
  Reviewed and accepted as a known risk on 7 Sep 2026.
- Keep it in `require-dev`, never `require`. Deploy runs `composer install --no-dev`,
  and the production server cannot reach GitHub — a `require` entry makes every
  deploy fail.
- Config via `.env`: `QWEN_API_KEY`, `QWEN_BASE_URL` (defaults to DashScope intl endpoint), `QWEN_MODEL` (defaults to `qwen3.7-flash`).
- `storage/app/qwen-tasks/` is gitignored scratch space for task prompts/responses.

Use this for well-scoped, mechanical sub-tasks (boilerplate, repetitive edits, straightforward bugfixes) to save cost/time, while keeping architectural decisions and review with Claude.


## Qwen orchestrator/writer workflow (since 2026-09-15)

This project runs a two-model split inside Qwen Code, documented in
[`docs/ORCHESTRATOR.md`](docs/ORCHESTRATOR.md). It supersedes the "Claude applies the
patch by hand" pattern above for work done in Qwen Code; `php artisan qwen:agent` still
exists for one-shot read-only diff proposals.

- **Qwen 3.8-Flash = lead.** Plans, writes SPECs, reviews, commits. Never authors
  application code — not even a one-line fix.
- **Qwen 3.5-Flash = writer.** Authors 100% of the application code from a written work
  order, runs the verification steps, reports into `.qwen/runs/`.
- Paper trail: `.qwen/specs/SPEC-NNN-<slug>.md` → `.qwen/runs/RUN-NNN.md` →
  `.qwen/reviews/REVIEW-NNN-<slug>.md` → commit. Hard cap of 3 REJECT rounds, then escalate.

Enforced by a `PreToolUse` hook, `.qwen/hooks/orchestrator-gate.py`:

- The lead may write only `.qwen/**` and `docs/**` in this repo (plus its own agent and
  memory files under `~/.qwen/`). Anything else — including this file — is refused.
- `git commit` is refused unless some `REVIEW-*.md` contains `Verdict: APPROVE` **and** that
  review post-dates the writer's run report **and** every changed file outside those two
  areas. Signing off cannot be skipped, and code edited after a review cannot ride along
  on an older approval.
- Emergency bypass only: `touch .qwen/hooks/GATE_OFF`, and record why in the review.

Four project facts this workflow already paid to discover:

1. `vendor/bin/*` is committed **without the exec bit**, so `vendor/bin/pint` dies with
   `Permission denied` on every clone, local and production. Use `php vendor/bin/pint …`
   (and the same for `phpunit`).
2. **`public/build/**` is tracked and production never runs vite** — `deploy.sh` and
   `.cpanel.yml` contain no npm step, so the server serves whatever hashed bundle is in git.
   Any `resources/js/**` change is incomplete until `npm run build` runs and the new hashed
   asset plus `public/build/manifest.json` are committed. A "still showing the old UI" report
   is one of: not deployed, not built, or the wrong page — in that order of likelihood.
3. `.gitignore` contains a line `.md`, which matches a path component named exactly `.md`;
   it is **not** `*.md`, and Markdown is tracked normally. Leave it alone.
4. The writer model confabulates. Observed: reporting a `PASS` for a command that cannot
   execute, and answering a three-step read-only check with zero tool calls and two invented
   facts. Treat every writer report as a claim to re-run, not evidence. Per-request
   `source`/`model`/token counts are auditable in `~/.qwen/usage/token-usage-YYYY-MM.jsonl`.
5. **Production PHP is missing the `fileinfo` extension.** Any code path that stores an
   uploaded file — even `mimes:`/`extensions:` validation aside — crashes with
   `Error: Class "finfo" not found` inside `league/mime-type-detection` the moment Laravel's
   local Flysystem driver is constructed (`UploadedFile::store()`/`storeAs()`). This is a
   server PHP-config issue, not fixable from application code: enable `fileinfo` in cPanel →
   **Select PHP Version → Extensions** (or **MultiPHP INI Editor**) for the app's PHP version.
   As defense-in-depth (2026-09-15), all `mimes:` validation rules across the codebase were
   also switched to `extensions:` (filename-extension check only, no MIME sniffing), since
   `mimes:` independently throws its own unhandled `LogicException` when no MIME guesser is
   available — same underlying `fileinfo` gap, different code path. If a fresh "Server Error"
   appears on any upload feature, check `fileinfo` first before re-diagnosing from scratch.

Deploy is unchanged: `git pull` then `bash deploy.sh` in the cPanel terminal (that is where
`artisan migrate --force` runs).

---

# Claude Memory (migrated from Claude to Zed)

The following memory entries were copied from Claude Code's project memory.

## Memory index

- [100% qwen coding delegation](qwen-100-percent-coding-delegation.md) — for this project, Claude never writes code directly: plan/review/find bugs only, qwen-agent authors every patch

## qwen-100-percent-coding-delegation

---
name: qwen-100-percent-coding-delegation
description: "User wants 100% of code authorship delegated to qwen-agent for this project — Claude does planning, code review, and bug-finding only, never originates code itself"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: a6f0173d-1481-49c3-843c-dee2ba136d68
  modified: 2026-09-13T16:03:39.883Z
---

For the mge-system project, the user has explicitly tightened the delegation model beyond what the global `~/.claude/CLAUDE.md` and project `CLAUDE.md` already describe: **all code authorship — new code and bug fixes alike — must be written by `qwen-agent`, not by Claude.** Claude's role is scoped to: planning/architecture decisions, scoping and writing the task prompt for qwen, reviewing qwen's output for correctness/quality, and diagnosing bugs (root cause) before handing the fix description to qwen.

This removes the "trivial one-liner, no round-trip needed" exception that the global CLAUDE.md otherwise allows — for this project, even small fixes go through qwen first.

**What still has to be done directly by Claude, because qwen-agent is structurally incapable of it** (it is read-only and has no shell/file-write access — confirmed and accepted by the user when this was made explicit):
- Applying qwen's proposed patch to the actual file (Edit/Write tool)
- Running builds/tests/migrations to verify the patch
- git add/commit/push and other repo operations
- Browser-based visual verification

Established working pattern (used successfully once already, see the "My Tasks" panel dead-CSS-class fix on 2026-09-13): Claude finds/diagnoses an issue → writes a precise task prompt to `storage/app/qwen-tasks/*.txt` describing the bug and the required fix shape → runs `qwen-agent <task>.txt --context=<file> --out=<out>.md` → Claude reviews qwen's proposed diff critically (round 1 had a subtle redundant-class issue Claude caught and sent back) → iterates with qwen until the fix is clean → Claude applies it → Claude verifies (build + visual check).

**Why:** the user wants to keep tight control over cost/spend by using the cheaper qwen model for all code generation, while relying on Claude specifically for judgment — architecture, review, and catching what a less capable model gets wrong or produces imperfectly, exactly as demonstrated in the "My Tasks" fix round-trip.

See also [[mge-system-project-overview]] if that memory exists, and the project's own `CLAUDE.md` "Qwen sub-agent" section for the mechanics (`qwen-agent` CLI location, env config, `storage/app/qwen-tasks/` scratch dir).

