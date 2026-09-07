<?php

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\LeaveEntitlementRule;
use App\Models\LeaveType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Works out how many days of a given leave type an employee is entitled to in a
 * given leave year (plan 7.2, 7.3).
 *
 * Three things decide the answer:
 *
 *   1. length of service, which selects a tier from leave_entitlement_rules
 *   2. what happens when an employee crosses a tier mid-year (tier_crossing)
 *   3. proration for someone who joined or left partway through the year
 *
 * The tier boundary convention is fixed at min_years <= service < max_years and
 * lives in exactly one place, resolveTier(). Plan 7.3.8 is emphatic about this:
 * if two parts of the codebase disagree about which side of a boundary an
 * employee sits on, staff at exactly 2.00 or 5.00 years get different answers
 * depending on which screen they look at, and the resulting HR complaints are
 * very hard to trace back.
 */
class EntitlementResolver
{
    /** @var Collection<int, LeaveEntitlementRule>|null */
    private ?Collection $rules;

    public function __construct(
        private readonly LeavePolicy $policy,
        ?Collection $rules = null,
    ) {
        $this->rules = $rules;
    }

    /**
     * Entitled days for this employee, leave type and leave year.
     */
    public function entitlementFor(Employee $employee, LeaveType $leaveType, int $year): float
    {
        $base = $this->baseEntitlement($employee, $leaveType, $year);
        $prorated = $this->applyProration($employee, $leaveType, $year, $base);

        return $this->round($prorated, $this->policy->get('proration_rounding', $leaveType->id));
    }

    /**
     * Length of service in years at a given date. Returns 0.0 when the employee
     * has no hire date rather than guessing — an unknown start date must not
     * silently award the top tier.
     */
    public function serviceYearsAt(Employee $employee, Carbon $at): float
    {
        $base = $employee->hire_date;

        if ($base === null) {
            return 0.0;
        }

        $base = Carbon::parse($base)->startOfDay();

        if ($at->lt($base)) {
            return 0.0;
        }

        return (float) $base->floatDiffInYears($at);
    }

    /**
     * The tier's days before any proration, honouring the tier_crossing policy.
     */
    private function baseEntitlement(Employee $employee, LeaveType $leaveType, int $year): float
    {
        $startOfYear = Carbon::create($year, 1, 1)->startOfDay();
        $endOfYear = Carbon::create($year, 12, 31)->startOfDay();

        $mode = $this->policy->get('tier_crossing', $leaveType->id);

        $atStart = $this->resolveTier($employee, $leaveType, $this->serviceYearsAt($employee, $startOfYear));
        $atEnd = $this->resolveTier($employee, $leaveType, $this->serviceYearsAt($employee, $endOfYear));

        return match ($mode) {
            // Highest tier reached at any point in the year applies to the whole year.
            'immediate' => max($atStart, $atEnd),

            // Weighted by how much of the year was spent in each tier.
            'prorate' => $this->prorateAcrossTiers($employee, $leaveType, $year, $atStart, $atEnd),

            // Default: the tier as at 1 January. Crossing a tier takes effect the
            // following year, so a balance never increases mid-year.
            default => $atStart,
        };
    }

    /**
     * Month-weighted blend of the two tiers, for tier_crossing = prorate.
     */
    private function prorateAcrossTiers(Employee $employee, LeaveType $leaveType, int $year, float $atStart, float $atEnd): float
    {
        if ($atStart === $atEnd) {
            return $atStart;
        }

        // Find the first month in which the employee sits in the higher tier.
        $crossingMonth = 13;

        for ($month = 1; $month <= 12; $month++) {
            $service = $this->serviceYearsAt($employee, Carbon::create($year, $month, 1)->startOfDay());

            if ($this->resolveTier($employee, $leaveType, $service) >= $atEnd) {
                $crossingMonth = $month;
                break;
            }
        }

        $monthsInLowerTier = $crossingMonth - 1;
        $monthsInHigherTier = 12 - $monthsInLowerTier;

        return ($atStart * $monthsInLowerTier / 12) + ($atEnd * $monthsInHigherTier / 12);
    }

    /**
     * Select the entitlement tier for a given length of service.
     *
     * ⭐ The single place the boundary convention is applied:
     *      min_years <= service < max_years
     * A null max_years means the tier is open-ended. This matches the wording of
     * the Employment Act itself ("2 years but less than 5 years"), so an employee
     * at exactly 5.00 years falls into the 5-and-above tier.
     *
     * Falls back to leave_types.default_days_per_year when no tier matches, so a
     * system where nobody has configured tiers still produces a sane number
     * rather than zero (plan 7.3.3a).
     */
    public function resolveTier(Employee $employee, LeaveType $leaveType, float $serviceYears): float
    {
        $candidates = $this->rulesFor($leaveType)
            ->filter(function (LeaveEntitlementRule $rule) use ($employee, $serviceYears) {
                if ($serviceYears < (float) $rule->min_years) {
                    return false;
                }

                if ($rule->max_years !== null && $serviceYears >= (float) $rule->max_years) {
                    return false;
                }

                if ($rule->staff_category !== null && strcasecmp($rule->staff_category, (string) $employee->category) !== 0) {
                    return false;
                }

                if ($rule->employment_type !== null && strcasecmp($rule->employment_type, (string) $employee->employment_type) !== 0) {
                    return false;
                }

                return true;
            });

        if ($candidates->isEmpty()) {
            return (float) $leaveType->default_days_per_year;
        }

        // A rule naming a category or employment type is more specific than a
        // blanket one, and wins.
        $best = $candidates
            ->sortByDesc(fn (LeaveEntitlementRule $r) => ($r->staff_category !== null ? 2 : 0) + ($r->employment_type !== null ? 1 : 0))
            ->first();

        return (float) $best->days;
    }

    /**
     * Reduce the entitlement for an employee who was not present for the whole
     * leave year — a mid-year joiner, or someone who left (plan 7.3.1).
     */
    private function applyProration(Employee $employee, LeaveType $leaveType, int $year, float $days): float
    {
        $startOfYear = Carbon::create($year, 1, 1)->startOfDay();
        $endOfYear = Carbon::create($year, 12, 31)->startOfDay();

        $from = $startOfYear;
        $to = $endOfYear;

        $hire = $employee->hire_date ? Carbon::parse($employee->hire_date)->startOfDay() : null;
        $exit = $this->exitDate($employee);

        $joinedMidYear = $hire !== null && $hire->gt($startOfYear) && $hire->lte($endOfYear);
        $leftMidYear = $exit !== null && $exit->gte($startOfYear) && $exit->lt($endOfYear);

        // Employed for none of this year at all.
        if (($hire !== null && $hire->gt($endOfYear)) || ($exit !== null && $exit->lt($startOfYear))) {
            return 0.0;
        }

        if ($joinedMidYear) {
            $from = $hire;
        }

        if ($leftMidYear && $this->policy->bool('exit_proration', $leaveType->id)) {
            $to = $exit;
        }

        if ($from->equalTo($startOfYear) && $to->equalTo($endOfYear)) {
            return $days;
        }

        // 'full' and 'none' both mean "do not prorate a new joiner". Exit
        // proration is governed by its own setting and still applies.
        $mode = $this->policy->get('new_joiner_proration', $leaveType->id);

        if ($joinedMidYear && in_array($mode, ['full', 'none'], true) && ! $leftMidYear) {
            return $days;
        }

        if ($mode === 'daily' || ($leftMidYear && ! $joinedMidYear)) {
            $daysInYear = $startOfYear->isLeapYear() ? 366 : 365;
            $servedDays = $from->diffInDays($to) + 1;

            return $days * $servedDays / $daysInYear;
        }

        // Monthly (the default): count each month in which the employee was
        // employed for any part of the month.
        $months = ((int) $to->format('n')) - ((int) $from->format('n')) + 1;

        return $days * max(0, $months) / 12;
    }

    private function exitDate(Employee $employee): ?Carbon
    {
        // Prorate to the last day actually worked when it is known; the
        // resignation notice date (resign_date) is only a fallback. Plan 9.4 is
        // explicit that the two differ and entitlement follows the last working
        // day.
        $exit = $employee->last_working_date ?? $employee->resign_date;

        return $exit ? Carbon::parse($exit)->startOfDay() : null;
    }

    /**
     * Rounding modes from plan 7.3.1.
     */
    public function round(float $value, ?string $mode): float
    {
        return match ($mode) {
            'none' => round($value, 2),
            'up_half' => ceil($value * 2) / 2,
            'nearest_whole' => (float) round($value),
            'up_whole' => (float) ceil($value),
            'down' => (float) floor($value),
            default => round($value * 2) / 2, // nearest_half
        };
    }

    /**
     * @return Collection<int, LeaveEntitlementRule>
     */
    private function rulesFor(LeaveType $leaveType): Collection
    {
        $this->rules ??= LeaveEntitlementRule::active()->get();

        return $this->rules->where('leave_type_id', $leaveType->id);
    }
}
