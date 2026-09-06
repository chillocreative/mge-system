<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('year');
            $table->decimal('fraction', 3, 2)->default(1.00);
            $table->boolean('is_deducted')->default(true);
            $table->string('exclusion_reason')->nullable();
            $table->timestamps();

            $table->unique(['leave_request_id', 'date']);
            $table->index(['employee_id', 'year', 'leave_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_days');
    }
};
