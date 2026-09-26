<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission templates per role. A role no longer grants permissions directly;
 * instead these presets are copied to a user's DIRECT permissions when the role
 * is assigned. Per-user access is then fully editable on the User Access page.
 */
class RolePresets
{
    private const BASE = [
        'dashboard.view',
        'leave.view',
        'leave.request',
        'training.request',
        'calendar.view',
        'memos.view',
    ];

    /**
     * @return array<string>
     */
    public static function for(?string $role): array
    {
        return match ($role) {
            'Admin & HR' => Permission::pluck('name')->all(),
            'Finances & HR' => self::financesHr(),
            'Projects' => self::projects(),
            'Employee', 'General Workers', 'Protege' => self::BASE,
            'Executives' => self::executives(),
            'Managers' => self::managers(),
            'Directors' => self::directors(),
            default => [],
        };
    }

    private static function executives(): array
    {
        return array_merge(self::BASE, [
            'projects.view',
            'clients.view',
            'documents.view',
            'meetings.view',
            'reports.view',
        ]);
    }

    private static function managers(): array
    {
        return array_merge(self::executives(), [
            'leave.approve',
            'staff.view',
            'training.view',
            'tasks.view',
        ]);
    }

    private static function directors(): array
    {
        return array_merge(self::managers(), [
            'finance.view',
            'payroll.view',
        ]);
    }

    private static function financesHr(): array
    {
        return [
            'dashboard.view', 'dashboard.view-finance-stats', 'dashboard.view-hr-stats',
            'users.view', 'users.create', 'users.edit', 'users.delete', 'users.approve',
            'departments.view', 'departments.create', 'departments.edit', 'departments.delete',
            'designations.view', 'designations.create', 'designations.edit', 'designations.delete',
            'projects.view',
            'tasks.view',
            'clients.view',
            'finance.view', 'finance.manage-budgets', 'finance.approve-expenses', 'finance.reports',
            'attendance.view', 'attendance.upload', 'attendance.delete',
            'payroll.view', 'payroll.generate', 'payroll.approve', 'payroll.email', 'payroll.ea-form',
            'staff.view', 'staff.create', 'staff.edit', 'staff.delete',
            'leave.view', 'leave.request', 'leave.approve', 'leave.manage',
            'training.view', 'training.request', 'training.approve', 'training.manage',
            'calendar.view', 'calendar.manage',
            'meetings.view', 'documents.view', 'drawings.view',
            'reports.view', 'reports.export',
            'safety.view',
            'environmental.view',
            'memos.view', 'memos.send-hr',
        ];
    }

    /**
     * Backfill DIRECT permissions for users who have exactly one role with a
     * known preset and no direct permissions of their own yet (e.g. seeded
     * users created before role presets existed, or users created before the
     * "syncPermissions on role assignment" behaviour was added).
     *
     * Idempotent: a user who already has any direct permission is left alone,
     * since that set may be intentionally customised on the User Access page.
     *
     * @return int number of users updated
     */
    public static function backfillDirectPermissions(): int
    {
        if (! Schema::hasTable('model_has_roles')) {
            return 0;
        }

        $updated = 0;

        try {
            $users = User::with('roles')->whereDoesntHave('permissions')->get();

            foreach ($users as $user) {
                if ($user->roles->count() !== 1) {
                    continue;
                }

                $roleName = $user->roles->first()->name;
                $preset = self::for($roleName);

                if (empty($preset)) {
                    continue;
                }

                $user->syncPermissions($preset);
                $updated++;
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            return $updated;
        }

        return $updated;
    }

    private static function projects(): array
    {
        return [
            'dashboard.view', 'dashboard.view-project-stats',
            'training.request',
            'users.view',
            'departments.view', 'designations.view',
            'projects.view', 'projects.create', 'projects.edit', 'projects.delete', 'projects.manage-members',
            'tasks.view', 'tasks.create', 'tasks.edit', 'tasks.delete', 'tasks.assign',
            'clients.view', 'clients.create', 'clients.edit', 'clients.delete',
            'finance.view',
            'reports.view', 'reports.manage',
            'safety.view', 'safety.create', 'safety.manage',
            'environmental.view', 'environmental.create', 'environmental.manage',
            'staff.view',
            'calendar.view', 'calendar.manage',
            'meetings.view', 'meetings.create', 'meetings.manage',
            'documents.view', 'documents.upload', 'documents.manage',
            'drawings.view', 'drawings.upload', 'drawings.manage',
            'assets.view', 'inventory.view', 'maintenance.view',
            'qc.view', 'qc.manage',
            'memos.view', 'memos.send-project',
        ];
    }
}
