<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /*
    |--------------------------------------------------------------------------
    | Permission Matrix — MGE-PMS
    |--------------------------------------------------------------------------
    |
    | Role 1: Admin & HR   → Full system access (super role)
    | Role 2: Finances & HR → Finance management + HR (users, departments)
    | Role 3: Projects      → Project lifecycle + tasks + client relations
    |
    */

    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ---------------------------------------------------------------------
        // 1. Define every granular permission in the system
        // ---------------------------------------------------------------------

        $permissions = [

            // Dashboard
            'dashboard.view',
            'dashboard.view-finance-stats',
            'dashboard.view-project-stats',
            'dashboard.view-hr-stats',

            // Users (HR)
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            // Departments (HR)
            'departments.view',
            'departments.create',
            'departments.edit',
            'departments.delete',

            // Designations (HR)
            'designations.view',
            'designations.create',
            'designations.edit',
            'designations.delete',

            // Projects
            'projects.view',
            'projects.create',
            'projects.edit',
            'projects.delete',
            'projects.manage-members',

            // Tasks
            'tasks.view',
            'tasks.create',
            'tasks.edit',
            'tasks.delete',
            'tasks.assign',

            // Clients
            'clients.view',
            'clients.create',
            'clients.edit',
            'clients.delete',

            // Finance / Budgets
            'finance.view',
            'finance.manage-budgets',
            'finance.approve-expenses',
            'finance.reports',

            // Attendance
            'attendance.view',
            'attendance.upload',
            'attendance.delete',

            // Payroll
            'payroll.view',
            'payroll.generate',
            'payroll.approve',

            // Reports
            'reports.view',
            'reports.export',

            // Roles & Permissions (system admin)
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',

            // System Settings
            'settings.view',
            'settings.manage',

            // Safety (OSHA)
            'safety.view',
            'safety.create',
            'safety.manage',

            // Environmental
            'environmental.view',
            'environmental.create',
            'environmental.manage',

            // Activity Logs
            'activity-logs.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // ---------------------------------------------------------------------
        // 2. Create roles and assign permissions
        // ---------------------------------------------------------------------

        /*
        |------------------------------------------------------------------
        | ROLE: Admin & HR  — Full unrestricted access
        |------------------------------------------------------------------
        | Gets every permission. Also granted super-admin Gate::before
        | bypass in AppServiceProvider so any future permissions are
        | automatically included without re-seeding.
        */
        $adminHr = Role::firstOrCreate(['name' => 'Admin & HR', 'guard_name' => 'web']);
        $adminHr->syncPermissions($permissions);

        /*
        |------------------------------------------------------------------
        | ROLE: Finances & HR
        |------------------------------------------------------------------
        | Full finance module access: budgets, expenses, finance reports.
        | Full HR access: users, departments, designations CRUD.
        | Full attendance + payroll access (upload, generate, approve).
        | Read-only on projects, tasks, clients (no mutations).
        | No access to: roles, settings, activity-logs.
        */
        $financesHr = Role::firstOrCreate(['name' => 'Finances & HR', 'guard_name' => 'web']);
        $financesHr->syncPermissions([
            // Dashboard
            'dashboard.view',
            'dashboard.view-finance-stats',
            'dashboard.view-hr-stats',

            // HR — Users (full CRUD)
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            // HR — Departments (full CRUD)
            'departments.view',
            'departments.create',
            'departments.edit',
            'departments.delete',

            // HR — Designations (full CRUD)
            'designations.view',
            'designations.create',
            'designations.edit',
            'designations.delete',

            // Projects (read-only)
            'projects.view',

            // Tasks (read-only)
            'tasks.view',

            // Clients (read-only)
            'clients.view',

            // Finance (full access)
            'finance.view',
            'finance.manage-budgets',
            'finance.approve-expenses',
            'finance.reports',

            // Attendance (full access)
            'attendance.view',
            'attendance.upload',
            'attendance.delete',

            // Payroll (full access)
            'payroll.view',
            'payroll.generate',
            'payroll.approve',

            // Reports
            'reports.view',
            'reports.export',

            // Safety (view-only)
            'safety.view',

            // Environmental (view-only)
            'environmental.view',
        ]);

        /*
        |------------------------------------------------------------------
        | ROLE: Projects
        |------------------------------------------------------------------
        | Full project lifecycle: projects, tasks, client relations.
        | Read-only on users (see team), departments, designations.
        | View-only on finance (budget column on projects).
        | No access to: HR mutations, roles, settings, activity-logs,
        |               attendance upload, payroll management.
        */
        $projects = Role::firstOrCreate(['name' => 'Projects', 'guard_name' => 'web']);
        $projects->syncPermissions([
            // Dashboard
            'dashboard.view',
            'dashboard.view-project-stats',

            // Users (read-only — see team members)
            'users.view',

            // Departments/Designations (read-only)
            'departments.view',
            'designations.view',

            // Projects (full CRUD)
            'projects.view',
            'projects.create',
            'projects.edit',
            'projects.delete',
            'projects.manage-members',

            // Tasks (full CRUD)
            'tasks.view',
            'tasks.create',
            'tasks.edit',
            'tasks.delete',
            'tasks.assign',

            // Clients (full CRUD — project relations)
            'clients.view',
            'clients.create',
            'clients.edit',
            'clients.delete',

            // Finance (view-only — see budgets on projects)
            'finance.view',

            // Reports (view-only)
            'reports.view',

            // Safety (full access)
            'safety.view',
            'safety.create',
            'safety.manage',

            // Environmental (full access)
            'environmental.view',
            'environmental.create',
            'environmental.manage',
        ]);
    }
}
