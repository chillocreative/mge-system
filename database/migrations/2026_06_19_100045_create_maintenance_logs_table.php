<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('maintainable');
            $table->enum('maintenance_type', ['preventive', 'corrective', 'emergency'])->default('preventive');
            $table->date('performed_date');
            $table->date('next_due_date')->nullable();
            $table->text('description');
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('vendor')->nullable();
            $table->string('performed_by')->nullable();
            $table->enum('status', ['planned', 'in_progress', 'completed'])->default('completed');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_logs');
    }
};
