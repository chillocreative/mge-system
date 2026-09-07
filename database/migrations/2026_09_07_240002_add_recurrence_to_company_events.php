<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_events', function (Blueprint $table) {
            $table->string('recurrence')->default('none')->after('all_day');
            $table->date('recurrence_until')->nullable()->after('recurrence');
        });
    }

    public function down(): void
    {
        Schema::table('company_events', function (Blueprint $table) {
            $table->dropColumn(['recurrence', 'recurrence_until']);
        });
    }
};
