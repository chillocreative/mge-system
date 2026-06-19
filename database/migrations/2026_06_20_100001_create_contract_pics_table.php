<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_pics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_contract_id')->constrained('project_contracts')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('designation')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('project_contract_id');
        });

        // Carry the existing single PIC into the new table so nothing is lost.
        $existing = DB::table('project_contracts')
            ->whereNotNull('pic_name')
            ->where('pic_name', '!=', '')
            ->get(['id', 'pic_name', 'pic_email', 'pic_phone']);

        foreach ($existing as $c) {
            DB::table('contract_pics')->insert([
                'project_contract_id' => $c->id,
                'name' => $c->pic_name,
                'email' => $c->pic_email,
                'phone' => $c->pic_phone,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_pics');
    }
};
