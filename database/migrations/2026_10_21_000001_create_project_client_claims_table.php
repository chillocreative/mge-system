<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // What the company claims from the client (income) — same shape as subcontractor claims,
    // but the counterparty is a record from the Clients module instead of free text.
    public function up(): void
    {
        Schema::create('project_client_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('claim_no')->nullable();
            $table->string('invoice_no')->nullable();
            $table->string('do_no')->nullable();
            $table->date('submitted_date')->nullable();
            $table->date('certified_date')->nullable();
            $table->date('paid_date')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('certified_amount', 15, 2)->nullable();
            $table->string('status')->default('submitted');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'submitted_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_client_claims');
    }
};
