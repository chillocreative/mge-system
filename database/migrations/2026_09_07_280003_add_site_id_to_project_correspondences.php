<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a correspondence to a project site (Ciri 25). Nullable and set null on
 * site delete, exactly like the other operational tables — existing rows get
 * NULL and deleting a site never deletes correspondence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_correspondences', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable()->after('project_id')
                ->constrained('project_sites')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_correspondences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_id');
        });
    }
};
