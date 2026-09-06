<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_policy_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('key');
            $table->string('value')->nullable();
            $table->date('effective_from')->nullable();
            $table->timestamps();

            $table->unique(['leave_type_id', 'key', 'effective_from'], 'lps_type_key_eff_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_policy_settings');
    }
};
