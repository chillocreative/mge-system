<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('environment_project_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained('projects')->cascadeOnDelete();
            $table->string('consultant_company')->nullable();
            $table->string('consultant_name')->nullable();
            $table->string('consultant_reg_no')->nullable();
            $table->string('officer_name')->nullable();
            $table->string('officer_reg_no')->nullable();
            $table->string('policy_image_path')->nullable();
            $table->string('location_map_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('environment_project_settings');
    }
};
