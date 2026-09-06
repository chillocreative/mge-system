<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->integer('default_days_per_year')->default(0);
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('leave_types')->insert([
            ['name' => 'Annual', 'code' => 'AL', 'default_days_per_year' => 14, 'is_paid' => true, 'requires_attachment' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sick', 'code' => 'MC', 'default_days_per_year' => 14, 'is_paid' => true, 'requires_attachment' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Emergency', 'code' => 'EL', 'default_days_per_year' => 3, 'is_paid' => true, 'requires_attachment' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Unpaid', 'code' => 'UL', 'default_days_per_year' => 0, 'is_paid' => false, 'requires_attachment' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Maternity', 'code' => 'ML', 'default_days_per_year' => 60, 'is_paid' => true, 'requires_attachment' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Hospitalization', 'code' => 'HL', 'default_days_per_year' => 60, 'is_paid' => true, 'requires_attachment' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
