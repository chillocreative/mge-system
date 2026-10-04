<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondence_rfa_subtypes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('correspondence_rfa_subtypes')->insert([
            ['code' => 'MA', 'name' => 'Material Approval', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'MS', 'name' => 'Method Statement', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'REPORT', 'name' => 'Report', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'TEST', 'name' => 'Test Result', 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'DWG', 'name' => 'Shop Drawing', 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondence_rfa_subtypes');
    }
};
