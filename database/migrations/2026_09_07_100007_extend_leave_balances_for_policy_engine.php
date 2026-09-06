<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->decimal('carried_forward', 5, 2)->default(0)->after('entitled_days');
            $table->decimal('adjustment_days', 5, 2)->default(0)->after('carried_forward');
            $table->json('rule_snapshot')->nullable()->after('remaining_days');
            $table->timestamp('calculated_at')->nullable()->after('rule_snapshot');
            $table->boolean('is_locked')->default(false)->after('calculated_at');
        });
    }

    public function down(): void
    {
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->dropColumn([
                'carried_forward',
                'adjustment_days',
                'rule_snapshot',
                'calculated_at',
                'is_locked',
            ]);
        });
    }
};
