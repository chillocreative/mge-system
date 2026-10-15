<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Project Managers decide leave and training for their office (see ApprovalRouting), so they must
 * be able to open those pages. Their role template ("Projects") has no leave access at all, so
 * grant the page permissions directly. Additive only.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['leave.view', 'leave.approve', 'training.view'];

    public function up(): void
    {
        foreach (['permissions', 'model_has_permissions', 'designations', 'users', 'employees'] as $table) {
            if (! Schema::hasTable($table)) {
                return;
            }
        }

        $permissionIds = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', self::PERMISSIONS)->pluck('id');
        $designationIds = DB::table('designations')->whereRaw('LOWER(TRIM(name)) = ?', ['project manager'])->pluck('id');

        if ($permissionIds->isEmpty() || $designationIds->isEmpty()) {
            return;
        }

        // Recognised by designation on the account or on the HR staff record, like the routing itself.
        $userIds = DB::table('users')->whereIn('designation_id', $designationIds)->whereNull('deleted_at')->pluck('id')
            ->merge(DB::table('employees')->whereIn('designation_id', $designationIds)->whereNotNull('user_id')->pluck('user_id'))
            ->unique();

        foreach ($userIds as $userId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('model_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $userId,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Not reversible: it is unknown which of these a user already held before the grant.
    }
};
