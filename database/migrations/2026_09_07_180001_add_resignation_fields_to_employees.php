<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('last_working_date')->nullable()->after('resign_date');
            $table->string('resignation_reason')->nullable()->after('last_working_date');
            $table->timestamp('status_changed_at')->nullable()->after('resignation_reason');
            $table->foreignId('status_changed_by')->nullable()->after('status_changed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_changed_by');
            $table->dropColumn('last_working_date', 'resignation_reason', 'status_changed_at');
        });
    }
};
