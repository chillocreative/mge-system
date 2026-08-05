<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE project_correspondences MODIFY status ENUM('open', 'pending', 'closed', 'declined') NOT NULL DEFAULT 'open'");
    }

    public function down(): void
    {
        DB::statement("UPDATE project_correspondences SET status = 'open' WHERE status = 'declined'");
        DB::statement("ALTER TABLE project_correspondences MODIFY status ENUM('open', 'pending', 'closed') NOT NULL DEFAULT 'open'");
    }
};
