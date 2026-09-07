<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hirarc_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hirarc_assessment_id')->constrained('hirarc_assessments')->cascadeOnDelete();
            $table->text('hazard');
            $table->text('risk')->nullable();
            $table->text('existing_control')->nullable();
            $table->unsignedTinyInteger('likelihood')->default(1);
            $table->unsignedTinyInteger('severity')->default(1);
            $table->unsignedTinyInteger('risk_rating')->default(1);
            $table->string('risk_level')->default('low');
            $table->text('recommended_control')->nullable();
            $table->string('pic')->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('hirarc_assessment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hirarc_items');
    }
};
