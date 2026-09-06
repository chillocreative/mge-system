<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL keeps the native ENUM; other drivers (sqlite, used by the test
        // suite) have no MODIFY, so use a plain string column instead.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE project_correspondences MODIFY status ENUM('open', 'pending', 'closed', 'declined') NOT NULL DEFAULT 'open'");

            return;
        }

        Schema::table('project_correspondences', function (Blueprint $table) {
            $table->string('status')->default('open')->change();
        });
    }

    public function down(): void
    {
        DB::table('project_correspondences')->where('status', 'declined')->update(['status' => 'open']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE project_correspondences MODIFY status ENUM('open', 'pending', 'closed') NOT NULL DEFAULT 'open'");

            return;
        }

        Schema::table('project_correspondences', function (Blueprint $table) {
            $table->string('status')->default('open')->change();
        });
    }
};
