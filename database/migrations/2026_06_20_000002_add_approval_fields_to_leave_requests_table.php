<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->enum('current_approval_level', ['manager', 'director'])->nullable()->default('manager')->after('status');
            $table->foreignId('manager_approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('manager_approved_at')->nullable()->after('manager_approved_by');
            $table->foreignId('director_approved_by')->nullable()->after('manager_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('director_approved_at')->nullable()->after('director_approved_by');
        });

        // Existing pending requests start at the manager stage.
        DB::table('leave_requests')->where('status', 'pending')->update(['current_approval_level' => 'manager']);
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_approved_by');
            $table->dropConstrainedForeignId('director_approved_by');
            $table->dropColumn(['current_approval_level', 'manager_approved_at', 'director_approved_at']);
        });
    }
};
