<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_invoices', function (Blueprint $table) {
            $table->decimal('wjp_current', 15, 2)->nullable();
            $table->decimal('wjp_cumulative', 15, 2)->nullable();
            $table->decimal('certified_current', 15, 2)->nullable();
            $table->decimal('certified_cumulative', 15, 2)->nullable();
            $table->date('evaluation_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('project_invoices', function (Blueprint $table) {
            $table->dropColumn(['wjp_current', 'wjp_cumulative', 'certified_current', 'certified_cumulative', 'evaluation_date']);
        });
    }
};
