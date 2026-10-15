<?php

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveDay;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Support\Carbon;

/**
 * The seam between the leave policy engine and the existing LeaveService.
 *
 * Everything here is gated on config('leave.engine_enabled'), which defaults to
 * false. MGE-PMS is already live, and switching this on changes how many days a
 * request costs and what balance each employee sees — so it is flipped
 * deliberately, after the shadow-mode comparison (plan 27.14 stage P3), not as a
 * side effect of a deploy.
 *
 * Keeping the seam in one class means LeaveService carries a single `if` rather
 * than being rewritten, and the old behaviour stays reachable and reviewable.
 */
class LeaveEngine
{
    public function __construct(
        private readonly LeaveDayCalculator $calculator,
        private readonly LeaveBalanceCalculator $balances,
        private readonly LeavePolicy $policy,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('leave.engine_enabled', false);
    }

    public function calculate(Employee $employee, $start, $end, bool $halfDay = false): CalculatedLeave
    {
        return $this->calculator->calculate($employee, $start, $end, $halfDay);
    }

    public function summary(Employee $employee, LeaveType $leaveType, int $year): array
    {
        return $this->balances->summary($employee, $leaveType, $year);
    }

    /**
     * Days this request goes beyond the employee's balance, summed over the leave years it
     * touches (a request spanning New Year draws on two separate balances).
     *
     * Informational: an over-balance request is still accepted, flagged for the approver, and
     * HR decides at the recording step whether the excess is declared unpaid. Unpaid types have
     * no balance, and a type that allows a negative balance may legitimately overdraw.
     */
    public function shortfall(Employee $employee, LeaveType $leaveType, CalculatedLeave $calculated): float
    {
        if (! $leaveType->is_paid || $this->policy->bool('allow_negative_balance', $leaveType->id)) {
            return 0.0;
        }

        $short = 0.0;

        foreach ($calculated->deductedByYear() as $year => $days) {
            $available = $this->balances->available($employee, $leaveType, $year);
            $short += max(0.0, $days - max($available, 0.0));
        }

        return round($short, 1);
    }

    public function available(Employee $employee, LeaveType $leaveType, int $year): float
    {
        return $this->balances->available($employee, $leaveType, $year);
    }

    /**
     * Store one row per calendar day (plan 27.13).
     */
    public function persistDays(LeaveRequest $request, CalculatedLeave $calculated): void
    {
        $now = now();

        LeaveDay::insert(array_map(fn (array $day) => [
            'leave_request_id' => $request->id,
            'employee_id' => $request->employee_id,
            'leave_type_id' => $request->leave_type_id,
            'date' => $day['date'],
            'year' => $day['year'],
            'fraction' => $day['fraction'],
            'is_deducted' => $day['is_deducted'],
            'exclusion_reason' => $day['exclusion_reason'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $calculated->days));
    }

    /**
     * Drop the per-day rows for a cancelled or rejected request, so the balance
     * recovers on its own (plan 27.13 — "cuti dibatalkan: padam baris leave_days
     * sahaja; baki pulih automatik").
     */
    public function releaseDays(LeaveRequest $request): void
    {
        LeaveDay::where('leave_request_id', $request->id)->delete();
    }

    /**
     * Write the balance snapshot for a leave year.
     *
     * rule_snapshot records the policy the number was produced under, so a later
     * settings change cannot retroactively rewrite a balance that has already
     * been used to approve leave (plan 7.3.3b). A locked year is never touched.
     */
    public function syncBalance(Employee $employee, LeaveType $leaveType, int $year): ?LeaveBalance
    {
        $existing = LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('year', $year)
            ->first();

        if ($existing?->is_locked) {
            return $existing;
        }

        $summary = $this->summary($employee, $leaveType, $year);

        return LeaveBalance::updateOrCreate(
            ['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'year' => $year],
            [
                'entitled_days' => $summary['entitled'],
                'carried_forward' => $summary['carried_forward'],
                'adjustment_days' => $summary['adjustment'],
                'used_days' => $summary['taken'],
                // Unpaid leave has no entitlement, so it never shows a negative balance.
                'remaining_days' => $leaveType->is_paid ? $summary['available'] : 0,
                'rule_snapshot' => $this->policy->snapshot($leaveType->id),
                'calculated_at' => Carbon::now(),
            ],
        );
    }
}
