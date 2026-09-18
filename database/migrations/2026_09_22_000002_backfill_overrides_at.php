<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('monthly_report_sections', 'overrides_at')) {
            return;
        }

        DB::table('monthly_report_sections')
            ->whereNotNull('overrides')
            ->whereNull('overrides_at')
            ->update([
                'overrides_at' => DB::raw('COALESCE(regenerated_at, updated_at)'),
            ]);
    }

    public function down(): void
    {
        // No-op: backfilling a timestamp is not reversible in a meaningful way.
    }
};
