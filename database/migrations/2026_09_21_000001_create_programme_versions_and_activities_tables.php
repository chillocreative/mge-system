<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_programme_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('label', 120);
            $table->date('status_date')->nullable();
            $table->string('source_type', 20);
            $table->string('source_file_path')->nullable();
            $table->string('source_file_name')->nullable();
            $table->boolean('is_current')->default(false);
            $table->unsignedInteger('activity_count')->default(0);
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'is_current']);
        });

        Schema::create('programme_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained('project_programme_versions')->cascadeOnDelete();
            $table->unsignedInteger('seq');
            $table->unsignedTinyInteger('outline_level')->default(1);
            $table->string('name', 255);
            $table->unsignedInteger('duration_days')->nullable();
            $table->date('start')->nullable();
            $table->date('finish')->nullable();
            $table->decimal('actual_pct', 5, 2)->nullable();
            $table->decimal('plan_pct', 5, 2)->nullable();
            $table->boolean('is_summary')->default(false);
            $table->index(['version_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_activities');
        Schema::dropIfExists('project_programme_versions');
    }
};
