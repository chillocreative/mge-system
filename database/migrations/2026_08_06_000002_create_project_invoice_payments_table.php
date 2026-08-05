<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_invoice_id')->constrained('project_invoices')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('document_no')->nullable();
            $table->enum('method', ['cash', 'online_transfer', 'cheque'])->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('project_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_invoice_payments');
    }
};
