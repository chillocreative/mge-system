<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('expense_date');
            $table->string('category')->nullable();
            $table->string('description')->nullable();
            $table->string('vendor')->nullable();
            $table->string('invoice_no')->nullable();
            $table->string('do_no')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status')->default('recorded');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'expense_date']);
        });
        Schema::create('project_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->string('category')->nullable();
            $table->decimal('budgeted_cost', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'month', 'category']);
        });
        Schema::create('project_vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('vendor')->nullable();
            $table->string('invoice_no')->nullable();
            $table->string('do_no')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('submitted_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('paid_date')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status')->default('submitted');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'submitted_date']);
        });
        Schema::create('project_subcontractor_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('subcontractor')->nullable();
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
        Schema::dropIfExists('project_subcontractor_claims');
        Schema::dropIfExists('project_vendor_payments');
        Schema::dropIfExists('project_budgets');
        Schema::dropIfExists('project_expenses');
    }
};
