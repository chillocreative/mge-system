<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_contracts', function (Blueprint $table) {
            $table->boolean('is_main')->default(false)->after('status');
            $table->decimal('contract_sum', 15, 2)->nullable();
            $table->decimal('performance_bond_amount', 15, 2)->nullable();
            $table->unsignedSmallInteger('duration_months')->nullable();
            $table->unsignedSmallInteger('dlp_months')->nullable();
            $table->decimal('lad_per_day', 12, 2)->nullable();
            $table->date('possession_date')->nullable();
            $table->date('completion_date')->nullable();
            $table->date('dlp_start_date')->nullable();
            $table->date('dlp_end_date')->nullable();
            $table->string('cidb_registration')->nullable();
            $table->json('insurances')->nullable();
            $table->index(['project_id', 'is_main']);
        });
    }

    public function down(): void
    {
        Schema::table('project_contracts', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'is_main']);
            $table->dropColumn(['is_main', 'contract_sum', 'performance_bond_amount', 'duration_months', 'dlp_months', 'lad_per_day', 'possession_date', 'completion_date', 'dlp_start_date', 'dlp_end_date', 'cidb_registration', 'insurances']);
        });
    }
};
