<?php

use App\Support\RolePresets;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('model_has_roles')) {
            return;
        }

        try {
            RolePresets::backfillDirectPermissions();
        } catch (\Throwable $e) {
            // A fresh install with no roles/permissions seeded yet should not
            // block migration; the seeder will populate them.
        }
    }

    public function down(): void
    {
        // No-op: backfilling direct permissions is not reversible in a
        // meaningful way (we don't know which were already there before).
    }
};
