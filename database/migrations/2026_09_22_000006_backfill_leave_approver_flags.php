<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('model_has_roles') || ! Schema::hasTable('roles')) {
            return;
        }

        try {
            $this->backfillFlagForRole('Managers', 'is_manager');
            $this->backfillFlagForRole('Directors', 'is_director');
        } catch (\Throwable $e) {
            // A fresh install with no roles seeded yet should not block migration.
        }
    }

    private function backfillFlagForRole(string $roleName, string $flagColumn): void
    {
        $roleId = DB::table('roles')->where('name', $roleName)->value('id');

        if (! $roleId) {
            return;
        }

        $userIds = DB::table('model_has_roles')
            ->where('role_id', $roleId)
            ->where('model_type', \App\Models\User::class)
            ->pluck('model_id');

        if ($userIds->isEmpty()) {
            return;
        }

        DB::table('users')
            ->whereIn('id', $userIds)
            ->where($flagColumn, false)
            ->update([$flagColumn => true]);
    }

    public function down(): void
    {
        // No-op: backfilling these flags is not reversible in a meaningful way.
    }
};
