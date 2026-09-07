<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('chassis_no')->nullable()->after('model');
            $table->string('engine_no')->nullable()->after('chassis_no');
            $table->string('serial_no')->nullable()->after('engine_no');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['chassis_no', 'engine_no', 'serial_no']);
        });
    }
};
