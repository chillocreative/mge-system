<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\PayrollRecord;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PayrollService
{
    /**
     * Generate payroll records for all active employees in a period.
     *
     * @return array{generated: int, errors: array}
     */
    public function generatePayroll(
        string $periodStart,
        string $periodEnd,
        int $generatedBy,
        ?float $baseSalaryOverride = null
    ): array {
        $config = config('payroll');
        $baseSalary = $baseSalaryOverride ?? $config['default_base_salary'];
        $workingDaysPerMonth = $config['working_days_per_month'];
        $workingHoursPerDay = $config['working_hours_per_day'];
        $overtimeMultiplier = $config['overtime_multiplier'];
        $deductAbsences = $config['deduct_absences'];

        $hourlyRate = round($baseSalary / ($workingDaysPerMonth * $workingHoursPerDay), 2);

        // Get attendance aggregates per user for the period in a single query
        $attendanceAgg = AttendanceRecord::query()
            ->forPeriod($periodStart, $periodEnd)
            ->select('user_id')
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw("SUM(CASE WHEN status IN ('present','late') THEN 1 ELSE 0 END) as present_days")
            ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days")
            ->selectRaw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days")
            ->selectRaw("SUM(CASE WHEN status = 'half_day' THEN 0.5 ELSE 0 END) as half_days")
            ->selectRaw('COALESCE(SUM(working_hours), 0) as total_working_hours')
            ->selectRaw('COALESCE(SUM(overtime_hours), 0) as total_overtime_hours')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        // Get all active users who have attendance in the period
        $userIds = $attendanceAgg->keys()->toArray();
        if (empty($userIds)) {
            return ['generated' => 0, 'errors' => ['No attendance records found for the selected period.']];
        }

        $generated = 0;
        $errors = [];

        DB::transaction(function () use (
            $userIds, $attendanceAgg, $periodStart, $periodEnd,
            $baseSalary, $hourlyRate, $workingDaysPerMonth,
            $overtimeMultiplier, $deductAbsences, $generatedBy,
            &$generated, &$errors
        ) {
            foreach ($userIds as $userId) {
                try {
                    $agg = $attendanceAgg[$userId];

                    $presentDays = (int) $agg->present_days + (float) $agg->half_days;
                    $absentDays = (int) $agg->absent_days;
                    $lateDays = (int) $agg->late_days;
                    $totalWorkingHours = round((float) $agg->total_working_hours, 2);
                    $totalOvertimeHours = round((float) $agg->total_overtime_hours, 2);

                    // Calculate pay
                    $overtimePay = round($totalOvertimeHours * $hourlyRate * $overtimeMultiplier, 2);

                    $absenceDeduction = 0;
                    if ($deductAbsences && $absentDays > 0) {
                        $dailyRate = round($baseSalary / $workingDaysPerMonth, 2);
                        $absenceDeduction = round($absentDays * $dailyRate, 2);
                    }

                    // Resolve linked employee for statutory + payslip particulars
                    $employee = Employee::where('user_id', $userId)->first();
                    $monthlyWage = $employee && (float) $employee->base_salary > 0
                        ? (float) $employee->base_salary
                        : $baseSalary;

                    $gross = round($monthlyWage + $overtimePay, 2);
                    $stat = $this->calculateStatutory($monthlyWage);

                    $totalDeductions = round(
                        $absenceDeduction
                        + $stat['epf_employee']
                        + $stat['socso_employee']
                        + $stat['eis_employee'],
                        2
                    );
                    $netSalary = round($gross - $totalDeductions, 2);

                    PayrollRecord::updateOrCreate(
                        [
                            'user_id' => $userId,
                            'period_start' => $periodStart,
                            'period_end' => $periodEnd,
                        ],
                        [
                            'employee_id' => $employee?->id,
                            'total_working_days' => $workingDaysPerMonth,
                            'total_present_days' => $presentDays,
                            'total_absent_days' => $absentDays,
                            'total_late_days' => $lateDays,
                            'total_working_hours' => $totalWorkingHours,
                            'total_overtime_hours' => $totalOvertimeHours,
                            'base_salary' => $monthlyWage,
                            'gross_salary' => $gross,
                            'allowances' => 0,
                            'bonus' => 0,
                            'hourly_rate' => $hourlyRate,
                            'overtime_pay' => $overtimePay,
                            'epf_employee' => $stat['epf_employee'],
                            'epf_employer' => $stat['epf_employer'],
                            'socso_employee' => $stat['socso_employee'],
                            'socso_employer' => $stat['socso_employer'],
                            'eis_employee' => $stat['eis_employee'],
                            'eis_employer' => $stat['eis_employer'],
                            'pcb' => 0,
                            'zakat' => 0,
                            'deductions' => $totalDeductions,
                            'net_salary' => max(0, $netSalary),
                            'status' => 'draft',
                            'generated_by' => $generatedBy,
                        ]
                    );

                    $generated++;
                } catch (\Throwable $e) {
                    $errors[] = "User #{$userId}: {$e->getMessage()}";
                }
            }
        });

        return ['generated' => $generated, 'errors' => $errors];
    }

    /**
     * List payroll records with filters.
     */
    public function listRecords(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = PayrollRecord::with('user:id,first_name,last_name,email,department_id')
            ->with('user.department:id,name')
            ->with('employee:id,employee_no,first_name,last_name,email');

        if (!empty($filters['user_id'])) {
            $query->forUser((int) $filters['user_id']);
        }

        if (!empty($filters['period_start']) && !empty($filters['period_end'])) {
            $query->forPeriod($filters['period_start'], $filters['period_end']);
        }

        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        return $query->latest('period_start')->paginate($perPage);
    }

    /**
     * Get a single payroll record with all relations.
     */
    public function getRecord(int $id): PayrollRecord
    {
        return PayrollRecord::with([
            'user:id,first_name,last_name,email,department_id,designation_id',
            'user.department:id,name',
            'user.designation:id,name',
            'employee.department:id,name',
            'employee.designation:id,name',
            'deductionItems',
            'generator:id,first_name,last_name',
            'approver:id,first_name,last_name',
        ])->findOrFail($id);
    }

    /**
     * Compute Malaysian statutory deductions (EPF, SOCSO, EIS) for a monthly wage.
     * Rates are configurable in config/payroll.php and overridable per payslip.
     */
    public function calculateStatutory(float $wage): array
    {
        $s = config('payroll.statutory');

        $epfEmployee = $epfEmployer = 0.0;
        if ($s['epf']['enabled'] ?? false) {
            $epfEmployee = (float) ceil($wage * $s['epf']['employee_rate']);
            $rate = $wage <= $s['epf']['employer_wage_threshold']
                ? $s['epf']['employer_rate_low']
                : $s['epf']['employer_rate_high'];
            $epfEmployer = (float) ceil($wage * $rate);
        }

        $socsoEmployee = $socsoEmployer = 0.0;
        if ($s['socso']['enabled'] ?? false) {
            $base = min($wage, (float) $s['socso']['wage_ceiling']);
            $socsoEmployee = min(round($base * $s['socso']['employee_rate'], 2), (float) $s['socso']['employee_max']);
            $socsoEmployer = min(round($base * $s['socso']['employer_rate'], 2), (float) $s['socso']['employer_max']);
        }

        $eisEmployee = $eisEmployer = 0.0;
        if ($s['eis']['enabled'] ?? false) {
            $base = min($wage, (float) $s['eis']['wage_ceiling']);
            $eisEmployee = min(round($base * $s['eis']['employee_rate'], 2), (float) $s['eis']['employee_max']);
            $eisEmployer = min(round($base * $s['eis']['employer_rate'], 2), (float) $s['eis']['employer_max']);
        }

        return [
            'epf_employee' => $epfEmployee,
            'epf_employer' => $epfEmployer,
            'socso_employee' => $socsoEmployee,
            'socso_employer' => $socsoEmployer,
            'eis_employee' => $eisEmployee,
            'eis_employer' => $eisEmployer,
        ];
    }

    /**
     * Recalculate a payslip after HR adjusts allowances/bonus/PCB/zakat and
     * manual deduction line items (penalty / cash advance / personal loan).
     */
    public function recalculate(int $id, array $data): PayrollRecord
    {
        return DB::transaction(function () use ($id, $data) {
            $record = PayrollRecord::findOrFail($id);

            $allowances = round((float) ($data['allowances'] ?? $record->allowances), 2);
            $bonus = round((float) ($data['bonus'] ?? $record->bonus), 2);
            $pcb = round((float) ($data['pcb'] ?? $record->pcb), 2);
            $zakat = round((float) ($data['zakat'] ?? $record->zakat), 2);

            if (array_key_exists('deduction_items', $data)) {
                $record->deductionItems()->delete();
                foreach ($data['deduction_items'] as $item) {
                    if (empty($item['amount'])) {
                        continue;
                    }
                    $record->deductionItems()->create([
                        'type' => $item['type'] ?? 'other',
                        'description' => $item['description'] ?? null,
                        'amount' => round((float) $item['amount'], 2),
                    ]);
                }
            }
            $manualTotal = (float) $record->deductionItems()->sum('amount');

            $wage = (float) $record->base_salary;
            $stat = $this->calculateStatutory($wage);
            $gross = round($wage + (float) $record->overtime_pay + $allowances + $bonus, 2);

            $config = config('payroll');
            $absenceDeduction = 0;
            if ($config['deduct_absences'] && $record->total_absent_days > 0) {
                $dailyRate = round($wage / $config['working_days_per_month'], 2);
                $absenceDeduction = round($record->total_absent_days * $dailyRate, 2);
            }

            $totalDeductions = round(
                $absenceDeduction + $stat['epf_employee'] + $stat['socso_employee']
                + $stat['eis_employee'] + $pcb + $zakat + $manualTotal,
                2
            );
            $net = round($gross - $totalDeductions, 2);

            $record->update([
                'allowances' => $allowances,
                'bonus' => $bonus,
                'pcb' => $pcb,
                'zakat' => $zakat,
                'gross_salary' => $gross,
                'epf_employee' => $stat['epf_employee'],
                'epf_employer' => $stat['epf_employer'],
                'socso_employee' => $stat['socso_employee'],
                'socso_employer' => $stat['socso_employer'],
                'eis_employee' => $stat['eis_employee'],
                'eis_employer' => $stat['eis_employer'],
                'deductions' => $totalDeductions,
                'net_salary' => max(0, $net),
            ]);

            return $this->getRecord($id);
        });
    }

    /**
     * Generate a payslip PDF (with MGE logo) for a payroll record.
     */
    public function generatePayslipPdf(int $id)
    {
        $record = $this->getRecord($id);

        return Pdf::loadView('pdf.payslip', [
            'record' => $record,
            'company' => config('payroll.company'),
            'currency' => config('payroll.currency'),
            'logo' => $this->logoData(),
        ])->setPaper('a4');
    }

    /**
     * Email the payslip PDF to the recipient employee.
     */
    public function emailPayslip(int $id): PayrollRecord
    {
        $record = $this->getRecord($id);
        $email = $record->employee?->email ?: $record->user?->email;

        if (! $email) {
            throw new \RuntimeException('No email address found for this payslip recipient.');
        }

        $pdf = $this->generatePayslipPdf($id)->output();
        Mail::to($email)->send(new \App\Mail\PayslipMail($record, $pdf));

        $record->update(['email_sent_at' => now()]);

        return $record->fresh();
    }

    /**
     * Generate the annual EA Form (C.P.8A) PDF for an employee.
     */
    public function generateEaForm(int $employeeId, int $year)
    {
        $employee = Employee::with(['department:id,name', 'designation:id,name'])->findOrFail($employeeId);

        $records = PayrollRecord::where('employee_id', $employeeId)
            ->whereYear('period_start', $year)
            ->get();

        $totals = [
            'gross_remuneration' => $records->sum(fn ($r) => (float) $r->base_salary + (float) $r->overtime_pay + (float) $r->allowances),
            'bonus' => $records->sum(fn ($r) => (float) $r->bonus),
            'epf_employee' => $records->sum(fn ($r) => (float) $r->epf_employee),
            'socso_employee' => $records->sum(fn ($r) => (float) $r->socso_employee),
            'eis_employee' => $records->sum(fn ($r) => (float) $r->eis_employee),
            'pcb' => $records->sum(fn ($r) => (float) $r->pcb),
            'zakat' => $records->sum(fn ($r) => (float) $r->zakat),
            'months' => $records->count(),
        ];

        return Pdf::loadView('pdf.ea-form', [
            'employee' => $employee,
            'year' => $year,
            'totals' => $totals,
            'company' => config('payroll.company'),
            'currency' => config('payroll.currency'),
            'logo' => $this->logoData(),
        ])->setPaper('a4');
    }

    private function logoData(): ?string
    {
        $path = public_path('logo.png');
        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
    }

    /**
     * Approve a draft payroll record.
     */
    public function approve(int $id, int $approvedBy): PayrollRecord
    {
        $record = PayrollRecord::findOrFail($id);

        if ($record->status !== 'draft') {
            throw new \InvalidArgumentException("Only draft payroll records can be approved. Current status: {$record->status}");
        }

        $record->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
        ]);

        return $record->fresh();
    }

    /**
     * Mark an approved payroll record as paid.
     */
    public function markPaid(int $id): PayrollRecord
    {
        $record = PayrollRecord::findOrFail($id);

        if ($record->status !== 'approved') {
            throw new \InvalidArgumentException("Only approved payroll records can be marked as paid. Current status: {$record->status}");
        }

        $record->update(['status' => 'paid']);

        return $record->fresh();
    }

    /**
     * Get payroll summary for a period.
     */
    public function getPeriodSummary(string $periodStart, string $periodEnd): array
    {
        return PayrollRecord::query()
            ->forPeriod($periodStart, $periodEnd)
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw('COALESCE(SUM(base_salary), 0) as total_base')
            ->selectRaw('COALESCE(SUM(overtime_pay), 0) as total_overtime')
            ->selectRaw('COALESCE(SUM(deductions), 0) as total_deductions')
            ->selectRaw('COALESCE(SUM(net_salary), 0) as total_net')
            ->selectRaw("SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count")
            ->selectRaw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count")
            ->selectRaw("SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count")
            ->first()
            ->toArray();
    }
}
