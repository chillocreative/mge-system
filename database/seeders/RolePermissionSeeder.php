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
            'users.approve',

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
            'payroll.email',
            'payroll.ea-form',

            // Staff (employee registry)
            'staff.view',
            'staff.create',
            'staff.edit',
            'staff.delete',

            // Leave
            'leave.view',
            'leave.request',
            'leave.approve',
            'leave.manage',

            // Training
            'training.view',
            'training.request',
            'training.approve',
            'training.manage',

            // Calendar
            'calendar.view',
            'calendar.manage',

            // Assets — vehicles
            'assets.view',
            'assets.manage',

            // Inventory
            'inventory.view',
            'inventory.manage',

            // Maintenance
            'maintenance.view',
            'maintenance.manage',

            // Meeting Minutes
            'meetings.view',
            'meetings.create',
            'meetings.manage',

            // Documents (company library)
            'documents.view',
            'documents.upload',
            'documents.manage',

            // Drawings
            'drawings.view',
            'drawings.upload',
            'drawings.manage',

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

            // Memos (internal memos)
            'memos.view',
            'memos.send-hr',
            'memos.send-project',

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
        // Roles are labels/templates only - they no longer grant permissions.
        // A user's access is their DIRECT permissions, seeded from App\Support\RolePresets
        // when a role is assigned (see UserService::applyRolePreset).
        foreach ([
            'Admin & HR', 'Finances & HR', 'Projects', 'Employee',
            'General Workers', 'Executives', 'Managers', 'Directors',
        ] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }
    }
}
