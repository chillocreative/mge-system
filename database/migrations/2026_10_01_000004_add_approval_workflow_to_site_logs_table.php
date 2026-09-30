<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_logs', function (Blueprint $table) {
            $table->foreignId('site_engineer_id')->nullable()->after('logged_by')->constrained('users')->nullOnDelete();
            $table->string('approval_status', 20)->default('pending')->after('site_engineer_id');
            $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('site_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('site_engineer_id');
            $table->dropColumn(['approval_status', 'approved_at']);
        });
    }
};
