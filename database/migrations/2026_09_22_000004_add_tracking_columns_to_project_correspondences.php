<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking columns to mirror the client's correspondence register:
 * Date Reminded, Status (Consultant), Status (Client). The two "Date
 * Approved" columns reuse the existing consultant_closed_date /
 * client_closed_date columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_correspondences', function (Blueprint $table) {
            $table->date('reminded_date')->nullable()->after('due_date');
            $table->string('consultant_status', 60)->nullable()->after('consultant_closed_date');
            $table->string('client_status', 60)->nullable()->after('client_closed_date');
        });
    }

    public function down(): void
    {
        Schema::table('project_correspondences', function (Blueprint $table) {
            $table->dropColumn(['reminded_date', 'consultant_status', 'client_status']);
        });
    }
};
