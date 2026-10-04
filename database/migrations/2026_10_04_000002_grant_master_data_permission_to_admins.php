<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('model_has_permissions') || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        $permissionId = DB::table('permissions')
            ->where('name', 'master-data.manage')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'master-data.manage',
                'guard_name' => 'web',
            ]);
        }

        $adminRoleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->where('name', 'Admin & HR')
            ->pluck('id');

        foreach (DB::table('model_has_roles')
            ->whereIn('role_id', $adminRoleIds)
            ->where('model_type', 'App\\Models\\User')
            ->pluck('model_id') as $userId) {
            DB::table('model_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'model_type' => 'App\\Models\\User',
                'model_id' => $userId,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('model_has_permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')
            ->where('name', 'master-data.manage')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId) {
            DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
        }
    }
};
