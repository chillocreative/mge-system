<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inputs for safety statistics (Ciri 25).
 *
 * LTIFR and the severity rate need two numbers the system did not previously
 * capture: man-hours worked, and days lost per injury.
 *
 * - safety_man_hours: man-hours are entered per project per month rather than
 *   derived from attendance. Attendance here is a daily upload without reliable
 *   hours, so a deliberate monthly figure is both simpler and more accurate
 *   than inferring it (plan Z6 — recorded, not inferred).
 * - days_lost on an incident marks a lost-time injury (days_lost > 0), the
 *   numerator for LTIFR and the basis for the severity rate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safety_man_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month'); // 1-12
            $table->unsignedInteger('man_hours');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One figure per project per month — re-entering overwrites.
            $table->unique(['project_id', 'year', 'month']);
        });

        Schema::table('safety_incidents', function (Blueprint $table) {
            $table->unsignedSmallInteger('days_lost')->nullable()->after('injury_description');
        });
    }

    public function down(): void
    {
        Schema::table('safety_incidents', function (Blueprint $table) {
            $table->dropColumn('days_lost');
        });
        Schema::dropIfExists('safety_man_hours');
    }
};
