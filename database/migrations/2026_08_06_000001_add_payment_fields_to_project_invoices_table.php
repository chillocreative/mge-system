<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_invoices', function (Blueprint $table) {
            $table->string('document_no')->nullable()->after('invoice_no');
            $table->date('date_paid')->nullable()->after('client_approved_date');
            $table->enum('payment_method', ['cash', 'online_transfer', 'cheque'])->nullable()->after('date_paid');
            $table->date('payment_to_subcon_date')->nullable()->after('payment_method');
            $table->string('claim_number')->nullable()->after('payment_to_subcon_date');
            $table->date('payment_cert_date')->nullable()->after('claim_number');
            $table->date('date_received_claim')->nullable()->after('payment_cert_date');
        });
    }

    public function down(): void
    {
        Schema::table('project_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'document_no',
                'date_paid',
                'payment_method',
                'payment_to_subcon_date',
                'claim_number',
                'payment_cert_date',
                'date_received_claim',
            ]);
        });
    }
};
