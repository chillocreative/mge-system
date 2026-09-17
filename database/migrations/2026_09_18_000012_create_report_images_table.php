<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('section', 30);
            $table->string('label')->nullable();
            $table->foreignId('period_id')->nullable()->constrained('project_progress_periods')->nullOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('caption')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->date('taken_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'section', 'period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_images');
    }
};
