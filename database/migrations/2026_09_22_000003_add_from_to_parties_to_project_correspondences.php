<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * From / To party selection on a correspondence: which party raised it and
 * which party it is addressed to (e.g. MGE -> Client, Client -> Consultant).
 * Purely additive and nullable — existing rows are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_correspondences', function (Blueprint $table) {
            $table->foreignId('from_party_id')->nullable()->after('current_party_id')->constrained('project_parties')->nullOnDelete();
            $table->foreignId('to_party_id')->nullable()->after('from_party_id')->constrained('project_parties')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_correspondences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('from_party_id');
            $table->dropConstrainedForeignId('to_party_id');
        });
    }
};
