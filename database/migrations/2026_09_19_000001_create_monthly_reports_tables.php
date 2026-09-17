<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('project_progress_periods')->cascadeOnDelete();
            $table->unsignedSmallInteger('report_no');
            $table->string('title');
            $table->string('month_label', 40);
            $table->date('evaluation_date')->nullable();
            $table->string('status', 10)->default('draft');
            $table->json('signatories')->nullable();
            $table->json('options')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalised_at')->nullable();
            $table->foreignId('finalised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'report_no']);
        });

        Schema::create('monthly_report_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('monthly_reports')->cascadeOnDelete();
            $table->string('key', 10);
            $table->string('title');
            $table->unsignedSmallInteger('sort_order');
            $table->boolean('include')->default(true);
            $table->json('data')->nullable();
            $table->json('overrides')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('regenerated_at')->nullable();
            $table->timestamps();
            $table->unique(['report_id', 'key']);
        });

        Schema::create('monthly_report_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('monthly_reports')->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_report_assets');
        Schema::dropIfExists('monthly_report_sections');
        Schema::dropIfExists('monthly_reports');
    }
};
