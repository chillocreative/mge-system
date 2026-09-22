<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_quality_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->date('sample_date');
            $table->time('sample_time')->nullable();
            $table->string('data_collector')->nullable();
            $table->string('witness')->nullable();
            $table->json('conditions')->nullable();
            $table->json('insitu')->nullable();
            $table->json('lab')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'sample_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_quality_records');
    }
};
