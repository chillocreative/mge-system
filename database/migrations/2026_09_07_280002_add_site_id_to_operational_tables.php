<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link operational records to a project site (Ciri 25 foundation).
 *
 * Every column here is nullable and set null on site delete, so this is safe on
 * a live database: existing rows get NULL (meaning "no specific site / see the
 * free-text location"), and deleting a site never deletes the records filed
 * under it — it just unlinks them.
 */
return new class extends Migration
{
    private array $tables = [
        'site_logs', 'safety_incidents', 'hazard_reports', 'work_permits',
        'hirarc_assessments', 'toolbox_meetings', 'compliance_checklists',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('site_id')->nullable()->after('project_id')
                    ->constrained('project_sites')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('site_id');
            });
        }
    }
};
