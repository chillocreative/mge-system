<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('environment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->unsignedInteger('report_no');
            $table->string('title');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 10)->default('draft');
            $table->json('signatories')->nullable();
            $table->json('sections')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('finalised_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'report_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('environment_reports');
    }
};
