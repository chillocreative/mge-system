<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_log_engineers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['site_log_id', 'user_id']);
        });

        DB::table('site_logs')
            ->whereNotNull('site_engineer_id')
            ->orderBy('id')
            ->chunkById(500, function ($logs): void {
                $now = now();
                $rows = $logs->map(fn ($log) => [
                    'site_log_id' => $log->id,
                    'user_id' => $log->site_engineer_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('site_log_engineers')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_log_engineers');
    }
};
