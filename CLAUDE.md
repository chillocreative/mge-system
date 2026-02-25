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
