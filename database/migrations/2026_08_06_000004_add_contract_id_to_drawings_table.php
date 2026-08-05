<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drawings', function (Blueprint $table) {
            $table->foreignId('contract_id')->nullable()->after('project_id')
                ->constrained('project_contracts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('drawings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contract_id');
        });
    }
};
