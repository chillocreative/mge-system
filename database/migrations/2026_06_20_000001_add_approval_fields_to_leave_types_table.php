<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('requires_director_approval')->default(false)->after('requires_attachment');
            $table->foreignId('manager_approver_id')->nullable()->after('requires_director_approval')->constrained('users')->nullOnDelete();
            $table->foreignId('director_approver_id')->nullable()->after('manager_approver_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_approver_id');
            $table->dropConstrainedForeignId('director_approver_id');
            $table->dropColumn('requires_director_approval');
        });
    }
};
