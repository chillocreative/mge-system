<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_members', function (Blueprint $table) {
            $table->string('designation')->nullable()->after('role');
            $table->foreignId('reports_to_user_id')->nullable()->after('designation')->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('org_sort')->default(0)->after('reports_to_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('project_members', function (Blueprint $table) {
            $table->dropForeign(['reports_to_user_id']);
            $table->dropColumn(['designation', 'reports_to_user_id', 'org_sort']);
        });
    }
};
