<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_expenses', function (Blueprint $table) {
            $table->decimal('quantity', 15, 3)->nullable();
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 15, 2)->nullable();
            $table->string('payment_method')->nullable();
            $table->boolean('amount_source_mismatch')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('project_expenses', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'unit', 'unit_price', 'payment_method', 'amount_source_mismatch']);
        });
    }
};
