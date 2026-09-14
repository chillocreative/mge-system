<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL keeps the native ENUM; other drivers (sqlite, used by the test
        // suite) have no MODIFY, so use a plain string column instead.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE project_documents MODIFY category ENUM('drawing', 'contract', 'permit', 'report', 'photo', 'specification', 'invoice', 'monthly_report', 'minute_meeting', 'progress_tracking', 'other') NOT NULL DEFAULT 'other'");

            return;
        }

        Schema::table('project_documents', function (Blueprint $table) {
            $table->string('category')->default('other')->change();
        });
    }

    public function down(): void
    {
        DB::table('project_documents')->whereIn('category', ['monthly_report', 'minute_meeting', 'progress_tracking'])->update(['category' => 'other']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE project_documents MODIFY category ENUM('drawing', 'contract', 'permit', 'report', 'photo', 'specification', 'invoice', 'other') NOT NULL DEFAULT 'other'");

            return;
        }

        Schema::table('project_documents', function (Blueprint $table) {
            $table->string('category')->default('other')->change();
        });
    }
};
