<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_log_machinery', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_log_id')->constrained('site_logs')->cascadeOnDelete();
            $table->string('machinery_type');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->index('site_log_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_log_machinery');
    }
};
