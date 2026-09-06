<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_entitlement_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->decimal('min_years', 4, 2)->default(0);
            $table->decimal('max_years', 4, 2)->nullable();
            $table->decimal('days', 5, 2);
            $table->string('staff_category')->nullable();
            $table->string('employment_type')->nullable();
            $table->date('effective_from')->nullable();
            $table->boolean('is_seed_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['leave_type_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_entitlement_rules');
    }
};
