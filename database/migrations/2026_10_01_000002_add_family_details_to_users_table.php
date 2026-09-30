<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('marital_status')->nullable()->after('emergency_contact_relationship');
            $table->unsignedInteger('number_of_children')->default(0)->after('marital_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['marital_status', 'number_of_children']);
        });
    }
};
