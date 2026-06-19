<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_correspondences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->enum('type', ['ncr', 'rfa', 'rfi', 'rfwi']);
            $table->string('reference_no')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['open', 'pending', 'closed'])->default('open');
            $table->date('raised_date');
            $table->date('due_date')->nullable();
            $table->text('response')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_correspondences');
    }
};
