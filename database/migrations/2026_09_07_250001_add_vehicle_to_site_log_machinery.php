<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a site-log machinery row to an Asset (Ciri 21).
 *
 * The machinery master lives in Assets (decision, 7 Sep) — specifically the
 * vehicles table, type = 'machinery'. This adds an optional reference so a site
 * log can point at a real asset instead of only free text. The existing
 * machinery_type string is kept and stays required, so every historical row
 * remains valid and free-text entry still works when an asset has not been
 * registered yet — no data migration needed, nothing breaks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_log_machinery', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('site_log_id')
                ->constrained('vehicles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('site_log_machinery', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_id');
        });
    }
};
