<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_log_weather_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_log_id')->constrained('site_logs')->cascadeOnDelete();
            $table->enum('condition', ['rain_start', 'rain_stop', 'overcast', 'clear']);
            $table->time('event_time');
            $table->timestamps();

            $table->index('site_log_id');
        });

        Schema::table('site_logs', function (Blueprint $table) {
            $table->dropColumn(['rain_start_time', 'rain_end_time', 'overcast_time', 'clear_time']);
        });
    }

    public function down(): void
    {
        Schema::table('site_logs', function (Blueprint $table) {
            $table->time('rain_start_time')->nullable()->after('weather');
            $table->time('rain_end_time')->nullable()->after('rain_start_time');
            $table->time('overcast_time')->nullable()->after('rain_end_time');
            $table->time('clear_time')->nullable()->after('overcast_time');
        });

        Schema::dropIfExists('site_log_weather_events');
    }
};
