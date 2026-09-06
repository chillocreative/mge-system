<?php

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveDay;
use App\Models\LeaveRequest;
use App\Models\LeaveType;

/**
 * Works out what an employee actually has left (plan 7.3.7, 8.4).
 *
 * Two things make this less obvious than "entitled minus taken":
 *
 * 1. Pending requests. If a second request is submitted while the first is still
 *    awaiting approval, both must be measured against the same remaining balance
 *    — otherwise an employee with 3 days left can submit two 3-day requests and
 *    have both approved (plan 8.4). Governed by deduct_pending_from_balance.
 *
 * 2. Quota pools. Sick and Hospitalisation leave share a 60-day cap rather than
 *    having independent quotas (plan 7.3.6). So the number shown for
 *    Hospitalisation must be the *effective* remaining figure — if 4 days of MC
 *    are already taken, it is 56, not 60. Plan 7.3.7 is explicit: showing the raw
 *    cap means an employee requests 60, gets refused at 56, and does not
 *    understand why. That complaint lands on HR, not on the system.
 */
class LeaveBalanceCalculator
{
    public function __construct(
        private readonly EntitlementResolver $entitlements,
        private readonly LeavePolicy $policy,
    ) {}

    /**
     * @return array{
     *     entitled: float, carried_forward: float, adjustment: float,
     *     taken: float, pending: float, available: float, pool: ?array
     * }
     */
    public function summary(Employee $employee, LeaveType $leaveType, int $year): array
    {
        $entitled = $this->entitlements->entitlementFor($employee, $leaveType, $year);

        $stored = LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('year', $year)
            ->first();

        $carried = (float) ($stored->carried_forward ?? 0);
        $adjustment = (float) ($stored->adjustment_days ?? 0);

        $taken = $this->daysUsed($employee, $leaveType, $year, ['approved']);
        $pending = $this->daysUsed($employee, $leaveType, $year, ['pending']);

        $countPending = $this->policy->bool('deduct_pending_from_balance', $leaveType->id);

        $available = $entitled + $carried + $adjustment - $taken - ($countPending ? $pending : 0.0);

        $pool = $this->poolSummary($employee, $leaveType, $year, $countPending);

        // The effective figure never exceeds what the shared pool still allows.
        if ($pool !== null) {
            $available = min($available, $pool['available']);
        }

        return [
            'entitled' => round($entitled, 2),
            'carried_forward' => round($carried, 2),
            'adjustment' => round($adjustment, 2),
            'taken' => round($taken, 2),
            'pending' => round($pending, 2),
            'available' => round($available, 2),
            'pool' => $pool,
        ];
    }

    public function available(Employee $employee, LeaveType $leaveType, int $year): float
    {
        return $this->summary($employee, $leaveType, $year)['available'];
    }

    /**
     * Days consumed for one leave type in one leave year.
     *
     * Prefers leave_days rows, which attribute each day to the year it actually
     * falls in — so a request spanning New Year is counted correctly on both
     * sides. Requests created before the engine existed have no leave_days rows,
     * so those fall back to days_count attributed to the start date's year. The
     * two paths are mutually exclusive per request, so nothing is double counted.
     *
     * @param  array<int, string>  $statuses
     */
    public function daysUsed(Employee $employee, LeaveType $leaveType, int $year, array $statuses): float
    {
        $requests = LeaveRequest::where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->whereIn('status', $statuses)
            ->get(['id', 'start_date', 'days_count']);

        if ($requests->isEmpty()) {
            return 0.0;
        }

        $withDays = LeaveDay::whereIn('leave_request_id', $requests->pluck('id'))
            ->where('is_deducted', true)
            ->where('year', $year)
            ->selectRaw('leave_request_id, SUM(fraction) as total')
            ->groupBy('leave_request_id')
            ->pluck('total', 'leave_request_id');

        $anyLeaveDays = LeaveDay::whereIn('leave_request_id', $requests->pluck('id'))
            ->pluck('leave_request_id')
            ->unique()
            ->flip();

        $total = 0.0;

        foreach ($requests as $request) {
            if ($anyLeaveDays->has($request->id)) {
                $total += (float) ($withDays[$request->id] ?? 0);

                continue;
            }

            // Legacy request: no per-day rows, so attribute the whole thing to
            // the year it started in.
            if ((int) date('Y', strtotime((string) $request->start_date)) === $year) {
                $total += (float) $request->days_count;
            }
        }

        return round($total, 2);
    }

    /**
     * Remaining days in the shared quota pool this type belongs to, if any.
     *
     * @return array{name: string, cap: float, used: float, available: float}|null
     */
    public function poolSummary(Employee $employee, LeaveType $leaveType, int $year, bool $countPending): ?array
    {
        $leaveType->loadMissing('quotaPool');
        $pool = $leaveType->quotaPool;

        if ($pool === null || ! $pool->is_active) {
            return null;
        }

        $members = LeaveType::where('quota_pool_id', $pool->id)->get();

        $cap = $pool->cap_source === 'sum_of_types'
            ? $members->sum(fn (LeaveType $t) => $this->entitlements->entitlementFor($employee, $t, $year))
            : (float) $pool->cap_days;

        $statuses = $countPending ? ['approved', 'pending'] : ['approved'];

        $used = $members->sum(
            fn (LeaveType $t) => $this->daysUsed($employee, $t, $year, $statuses),
        );

        return [
            'name' => $pool->name,
            'cap' => round((float) $cap, 2),
            'used' => round((float) $used, 2),
            'available' => round((float) $cap - (float) $used, 2),
        ];
    }
}
