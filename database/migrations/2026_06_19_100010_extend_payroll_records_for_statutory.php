<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_records', function (Blueprint $table) {
            // Earnings
            $table->decimal('gross_salary', 12, 2)->default(0)->after('base_salary');
            $table->decimal('allowances', 12, 2)->default(0)->after('gross_salary');
            $table->decimal('bonus', 12, 2)->default(0)->after('allowances');

            // Statutory contributions (Malaysian)
            $table->decimal('epf_employee', 12, 2)->default(0)->after('overtime_pay');
            $table->decimal('epf_employer', 12, 2)->default(0)->after('epf_employee');
            $table->decimal('socso_employee', 12, 2)->default(0)->after('epf_employer');
            $table->decimal('socso_employer', 12, 2)->default(0)->after('socso_employee');
            $table->decimal('eis_employee', 12, 2)->default(0)->after('socso_employer');
            $table->decimal('eis_employer', 12, 2)->default(0)->after('eis_employee');
            $table->decimal('pcb', 12, 2)->default(0)->after('eis_employer'); // MTD income tax (manual)
            $table->decimal('zakat', 12, 2)->default(0)->after('pcb'); // manual

            // Payslip delivery
            $table->string('payslip_path')->nullable()->after('notes');
            $table->timestamp('email_sent_at')->nullable()->after('payslip_path');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_records', function (Blueprint $table) {
            $table->dropColumn([
                'gross_salary', 'allowances', 'bonus',
                'epf_employee', 'epf_employer', 'socso_employee', 'socso_employer',
                'eis_employee', 'eis_employer', 'pcb', 'zakat',
                'payslip_path', 'email_sent_at',
            ]);
        });
    }
};
