<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('provider')->nullable();
            $table->string('category')->nullable();
            $table->date('training_date');
            $table->date('end_date')->nullable();
            $table->decimal('duration_days', 5, 1)->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->boolean('hrdf_claimable')->default(false);
            $table->enum('status', ['scheduled', 'completed'])->default('completed');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id']);
            $table->index(['training_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_records');
    }
};
