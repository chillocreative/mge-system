<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * One-time conversion to per-user permissions.
 *
 * Previously a user's access came from their role (shared by everyone with that
 * role). We copy each user's CURRENT effective permissions (role + direct) into
 * their DIRECT permissions, then strip permissions from all roles. Roles become
 * labels/templates only; per-user access is fully editable on the User Access
 * page. Idempotent — re-running is a no-op once roles hold no permissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function () {
            // 1. Pin each user's effective permissions as direct permissions.
            User::with('roles', 'permissions')->cursor()->each(function (User $user) {
                $effective = $user->getAllPermissions()->pluck('name')->all();
                $user->syncPermissions($effective);
            });

            // 2. Roles no longer grant permissions.
            Role::all()->each(fn (Role $role) => $role->syncPermissions([]));
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No safe automatic rollback — re-run the RolePermissionSeeder if needed.
    }
};
