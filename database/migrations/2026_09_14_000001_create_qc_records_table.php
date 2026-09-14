<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('qc_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->enum('type', ['inspection', 'ncr', 'material_test', 'audit']);
            $table->string('title');
            $table->string('reference_no')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'open', 'in_progress', 'resolved', 'closed'])->default('pending');
            $table->date('date');
            $table->string('location')->nullable();
            $table->text('findings')->nullable();
            $table->text('corrective_action')->nullable();
            $table->string('verified_by')->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qc_records');
    }
};
