<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_report_assets', function (Blueprint $table) {
            if (! Schema::hasColumn('monthly_report_assets', 'extension')) {
                $table->string('extension', 8)->nullable()->after('file_name');
            }
            if (! Schema::hasColumn('monthly_report_assets', 'size')) {
                $table->unsignedInteger('size')->nullable()->after('extension');
            }
            if (! Schema::hasColumn('monthly_report_assets', 'pages')) {
                $table->unsignedSmallInteger('pages')->nullable()->after('size');
            }
        });
    }

    public function down(): void
    {
        Schema::table('monthly_report_assets', function (Blueprint $table) {
            foreach (['extension', 'size', 'pages'] as $column) {
                if (Schema::hasColumn('monthly_report_assets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
