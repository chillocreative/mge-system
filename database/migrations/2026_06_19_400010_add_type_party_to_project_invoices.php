<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_invoices', function (Blueprint $table) {
            // client = MGE invoices the Client (revenue, with IPC attachments)
            // subcon = Subcontractor invoices MGE (cost)
            $table->enum('type', ['client', 'subcon'])->default('client')->after('project_id');
            $table->string('party_name')->nullable()->after('type'); // subcontractor / client name
            $table->index(['type']);
        });
    }

    public function down(): void
    {
        Schema::table('project_invoices', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'party_name']);
        });
    }
};
