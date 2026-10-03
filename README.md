# MGE-PMS

MGE-PMS is a construction project management system for managing projects, site operations, finance, human resources, safety, environmental compliance, correspondence, and internal collaboration from one web application.

## What it provides

- Project and client management, milestones, programmes, schedules, site logs, documents, events, contracts, parties, and progress reporting
- Monthly progress reports with programme data, S-curves, Gantt information, report images, registers, and PDF/Word export
- Project finance covering expenses, vendor payments, subcontractor claims, budgets, reports, invoices, and Excel import/export
- HR operations including staff records, user approval, attendance, payroll, leave, training, emergency contacts, and employee access control
- Safety workflows for HIRARC, permits, incidents, hazards, toolbox meetings, compliance checklists, and man-hours
- Environmental workflows for waste, site inspections, audits, water quality, environmental documents, and reporting
- Asset, vehicle, machinery, inventory, maintenance, and usage reporting
- Correspondence, internal email/memos, real-time chat, notifications, and calendar features
- Role- and permission-based access for Admin & HR, Finances & HR, Projects, and Employee users

## Technology

- **Backend:** Laravel 12, PHP 8.2+, MySQL, Laravel Sanctum
- **Frontend:** React 19, React Router 7, Vite 7, Tailwind CSS 4
- **Authorization:** Spatie Laravel Permission
- **Real-time features:** Laravel Broadcasting, Laravel Echo, and Pusher
- **Documents and data:** Dompdf, PHPWord, FPDF/FPDI, and Laravel Excel
- **Testing:** PHPUnit and Laravel feature tests

## Architecture

The backend follows a Controller → Service → Repository → Model structure:

- Controllers handle HTTP concerns and authorization boundaries.
- Services contain business rules and workflows.
- Repositories abstract data access.
- Models define persistence and relationships.

The React frontend keeps API calls in feature services, authentication in `AuthContext`, permission checks in the permission hook and gates, and protected navigation in route guards.

## Requirements

- PHP 8.2 or newer
- Composer
- Node.js and npm
- MySQL
- PHP extensions required by Laravel and the installed dependencies
- Pusher credentials for real-time functionality when enabled

## Local setup

```bash
git clone <repository-url>
cd mge-system
composer install
cp .env.example .env
php artisan key:generate
```

Configure the database, application URL, mail, storage, broadcasting, and Pusher values in `.env`, then run:

```bash
php artisan migrate
php artisan db:seed
php artisan storage:link
npm install
npm run build
```

For development, start the backend and frontend separately:

```bash
php artisan serve
npm run dev
```

The application is normally available at `http://mge-system.test` when using the configured Herd link, or at the URL reported by `php artisan serve`.

## Useful commands

```bash
# Run the automated test suite
php artisan test

# Format PHP files
php vendor/bin/pint

# Build production frontend assets
npm run build

# Seed roles and permissions
php artisan db:seed --class=RolePermissionSeeder
```

Production tracks the generated files in `public/build`, so run `npm run build` and commit the resulting asset manifest and bundles whenever frontend code changes.

## API

The API is defined in `routes/api.php`. Login and registration are public; authenticated endpoints use Sanctum and feature permissions. The main API areas include projects, project finance, monthly reports, tasks, users, HR, attendance, payroll, safety, environmental compliance, assets, correspondence, chat, email, notifications, and roles/permissions.

## Deployment

Deployments use the repository's deployment workflow and cPanel configuration. On a cPanel terminal, use the repository wrapper for Artisan commands because it selects a verified CLI PHP binary:

```bash
git pull
bash deploy.sh
```

Before deployment, verify the test suite, PHP formatting, frontend build, and migration status. Uploaded files require a working PHP `fileinfo` extension and a configured public storage link.

## Security and access

New registrations begin in a pending state and require administrator approval. Login is blocked for accounts that are not active. API authorization combines Sanctum authentication with Spatie roles and permissions, and resource-level checks are applied in the relevant controllers and services.

## Project documentation

Additional operational and feature documentation is available in `docs/`, including monthly report deployment guidance and the project development workflow.

## License

This project is based on Laravel and its dependencies. Refer to the repository owner for the application's distribution and licensing terms.
