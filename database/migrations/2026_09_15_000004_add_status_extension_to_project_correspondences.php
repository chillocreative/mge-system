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
            DB::statement("ALTER TABLE project_correspondences MODIFY status ENUM('open', 'pending', 'closed', 'declined', 'forwarded', 'others') NOT NULL DEFAULT 'open'");
        } else {
            Schema::table('project_correspondences', function (Blueprint $table) {
                $table->string('status')->default('open')->change();
            });
        }

        Schema::table('project_correspondences', function (Blueprint $table) {
            $table->string('other_status_text')->nullable()->after('status');
            $table->date('client_closed_date')->nullable()->after('expected_close_date');
            $table->date('consultant_closed_date')->nullable()->after('client_closed_date');
        });
    }

    public function down(): void
    {
        DB::table('project_correspondences')->whereIn('status', ['forwarded', 'others'])->update(['status' => 'open']);

        Schema::table('project_correspondences', function (Blueprint $table) {
            $table->dropColumn('other_status_text');
            $table->dropColumn('client_closed_date');
            $table->dropColumn('consultant_closed_date');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE project_correspondences MODIFY status ENUM('open', 'pending', 'closed', 'declined') NOT NULL DEFAULT 'open'");
        } else {
            Schema::table('project_correspondences', function (Blueprint $table) {
                $table->string('status')->default('open')->change();
            });
        }
    }
};
