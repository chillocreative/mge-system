<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('email_enabled')->default(true)->change();
        });

        // No preferences UI existed before this change, so every existing row
        // holds the old column default rather than a choice the user made.
        DB::table('notification_preferences')->update(['email_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('email_enabled')->default(false)->change();
        });
    }
};
