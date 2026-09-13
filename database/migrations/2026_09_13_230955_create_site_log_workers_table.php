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
        Schema::create('site_log_workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_log_id')->constrained('site_logs')->cascadeOnDelete();
            $table->string('worker_type');
            $table->unsignedInteger('count')->default(1);
            $table->timestamps();

            $table->index('site_log_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_log_workers');
    }
};
