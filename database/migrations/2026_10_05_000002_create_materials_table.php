<?php

use Database\Seeders\ProjectExpenseMaterialsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('category', 100);
            $table->string('description');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['category', 'description']);
            $table->index(['is_active', 'category']);
        });

        (new ProjectExpenseMaterialsSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
