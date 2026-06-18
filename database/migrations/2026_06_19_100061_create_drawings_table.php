<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drawings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('drawing_no')->unique();
            $table->string('reference_no')->nullable();
            $table->string('revision')->nullable();
            $table->string('tag')->nullable();
            $table->enum('discipline', ['architectural', 'structural', 'civil', 'mechanical', 'electrical', 'other'])->default('civil');
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('published');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tag');
            $table->index('drawing_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drawings');
    }
};
