<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\PayrollRecord;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

                    $deductions = 0;
                    if ($deductAbsences && $absentDays > 0) {
                        $dailyRate = round($baseSalary / $workingDaysPerMonth, 2);
                        $deductions = round($absentDays * $dailyRate, 2);
                    }

                    $netSalary = round($baseSalary + $overtimePay - $deductions, 2);

                    PayrollRecord::updateOrCreate(
                        [
                            'user_id' => $userId,
                            'period_start' => $periodStart,
                            'period_end' => $periodEnd,
                        ],
                        [
                            'total_working_days' => $workingDaysPerMonth,
                            'total_present_days' => $presentDays,
                            'total_absent_days' => $absentDays,
                            'total_late_days' => $lateDays,
                            'total_working_hours' => $totalWorkingHours,
                            'total_overtime_hours' => $totalOvertimeHours,
                            'base_salary' => $baseSalary,
                            'hourly_rate' => $hourlyRate,
                            'overtime_pay' => $overtimePay,
                            'deductions' => $deductions,
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
            ->with('user.department:id,name');

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
            'generator:id,first_name,last_name',
            'approver:id,first_name,last_name',
        ])->findOrFail($id);
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
