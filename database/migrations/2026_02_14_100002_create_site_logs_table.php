<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('weather', ['sunny', 'cloudy', 'rainy', 'stormy', 'windy', 'other'])->nullable();
            $table->unsignedInteger('workers_count')->default(0);
            $table->text('work_performed')->nullable();
            $table->text('materials_used')->nullable();
            $table->text('equipment_used')->nullable();
            $table->text('safety_notes')->nullable();
            $table->text('issues')->nullable();
            $table->foreignId('logged_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_logs');
    }
};
