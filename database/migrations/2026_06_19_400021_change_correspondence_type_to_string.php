<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Move from a fixed enum to a free string so admin-defined type codes work.
        Schema::table('project_correspondences', function (Blueprint $table) {
            $table->string('type')->change();
        });
    }

    public function down(): void
    {
        Schema::table('project_correspondences', function (Blueprint $table) {
            $table->enum('type', ['ncr', 'rfa', 'rfi', 'rfwi'])->change();
        });
    }
};
