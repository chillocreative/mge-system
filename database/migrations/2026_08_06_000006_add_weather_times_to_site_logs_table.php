<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_logs', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
        });

        Schema::table('site_logs', function (Blueprint $table) {
            $table->time('rain_start_time')->nullable()->after('weather');
            $table->time('rain_end_time')->nullable()->after('rain_start_time');
            $table->time('overcast_time')->nullable()->after('rain_end_time');
            $table->time('clear_time')->nullable()->after('overcast_time');
        });
    }

    public function down(): void
    {
        Schema::table('site_logs', function (Blueprint $table) {
            $table->dropColumn(['rain_start_time', 'rain_end_time', 'overcast_time', 'clear_time']);
        });

        DB::table('site_logs')->whereNull('title')->update(['title' => '']);

        Schema::table('site_logs', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
        });
    }
};
