<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('environment_report_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('environment_report_id')->constrained('environment_reports')->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('caption')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();

            $table->index(['environment_report_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('environment_report_assets');
    }
};
