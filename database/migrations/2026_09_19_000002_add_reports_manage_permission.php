<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('reports.manage', 'web');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Existing "Projects" role holders were assigned their permissions from the
        // RolePresets snapshot at the time their role was set; retroactively grant
        // reports.manage to the role itself (idempotent — givePermissionTo diffs
        // against current permissions) so existing Projects users pick it up too.
        $projectsRole = Role::where('name', 'Projects')->where('guard_name', 'web')->first();
        if ($projectsRole && ! $projectsRole->hasPermissionTo($permission)) {
            $projectsRole->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        Permission::where('name', 'reports.manage')->where('guard_name', 'web')->delete();
    }
};
