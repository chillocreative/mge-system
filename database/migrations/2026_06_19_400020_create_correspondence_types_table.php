<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondence_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();        // slug e.g. ncr, rfa, sst
            $table->string('name');                  // short label e.g. NCR
            $table->string('full_name')->nullable(); // e.g. Non-Conformance Report
            $table->string('color')->default('gray');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('correspondence_types')->insert([
            ['code' => 'ncr', 'name' => 'NCR', 'full_name' => 'Non-Conformance Report', 'color' => 'red', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'rfa', 'name' => 'RFA', 'full_name' => 'Request for Approval', 'color' => 'blue', 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'rfi', 'name' => 'RFI', 'full_name' => 'Request for Information', 'color' => 'amber', 'sort_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'rfwi', 'name' => 'RFWI', 'full_name' => 'Request for Work Inspection', 'color' => 'purple', 'sort_order' => 4, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondence_types');
    }
};
