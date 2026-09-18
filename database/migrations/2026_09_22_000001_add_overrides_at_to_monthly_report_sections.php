<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_report_sections', function (Blueprint $table) {
            if (! Schema::hasColumn('monthly_report_sections', 'overrides_at')) {
                $table->timestamp('overrides_at')->nullable()->after('overrides');
            }
        });
    }

    public function down(): void
    {
        Schema::table('monthly_report_sections', function (Blueprint $table) {
            if (Schema::hasColumn('monthly_report_sections', 'overrides_at')) {
                $table->dropColumn('overrides_at');
            }
        });
    }
};
