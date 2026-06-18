<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_record_id')->constrained('payroll_records')->cascadeOnDelete();
            $table->enum('type', ['penalty', 'cash_advance', 'personal_loan', 'other'])->default('other');
            $table->string('description')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();

            $table->index('payroll_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_deductions');
    }
};
