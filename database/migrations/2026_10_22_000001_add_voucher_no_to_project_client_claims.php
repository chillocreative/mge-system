<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // "No. Baucar" (voucher number) on a client claim. Nullable, so existing claims are untouched.
    public function up(): void
    {
        Schema::table('project_client_claims', function (Blueprint $table) {
            $table->string('voucher_no')->nullable()->after('certified_date');
        });
    }

    public function down(): void
    {
        Schema::table('project_client_claims', function (Blueprint $table) {
            $table->dropColumn('voucher_no');
        });
    }
};
