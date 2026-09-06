<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('date');
            $table->unsignedSmallInteger('year');
            $table->string('scope')->default('national');
            $table->string('state')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['date', 'name']);
            $table->index(['year', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_holidays');
    }
};
