<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_no')->unique();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('ic_passport_no')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->date('dob')->nullable();
            $table->text('address')->nullable();

            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'site_worker'])->default('full_time');
            $table->enum('category', ['office', 'site'])->default('office');
            $table->date('hire_date')->nullable();
            $table->date('resign_date')->nullable();
            $table->foreignId('reporting_manager_id')->nullable()->constrained('employees')->nullOnDelete();

            // Payroll / statutory particulars
            $table->string('bank_name')->nullable();
            $table->string('bank_account_no')->nullable();
            $table->string('epf_no')->nullable();
            $table->string('socso_no')->nullable();
            $table->string('tax_no')->nullable();
            $table->decimal('base_salary', 12, 2)->default(0);

            $table->enum('status', ['active', 'inactive', 'resigned'])->default('active');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('photo_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category']);
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
