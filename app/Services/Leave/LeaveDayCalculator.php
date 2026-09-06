<?php

namespace App\Services\Leave;

use App\Models\Employee;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Works out how many days a leave request actually costs the employee.
 *
 * Replaces the previous calculation, which was:
 *
 *     $days = $halfDay ? 0.5 : ($start->diffInDays($end) + 1);
 *
 * — raw calendar days, with no rest days, no public holidays and no cross-year
 * handling. Per plan 27.6b the correct formula is:
 *
 *     deducted = days in range
 *              − that employee's rest days (per their work pattern)
 *              − public holidays applicable to them
 *
 * Two employees requesting the same dates can legitimately be deducted different
 * amounts, because an office worker rests Saturday and Sunday while a site worker
 * rests only Sunday. That is correct, and it is why the per-day breakdown is
 * surfaced on the apply form rather than just a total.
 */
class LeaveDayCalculator
{
    public function __construct(
        private readonly WorkPatternResolver $patterns,
        private readonly HolidayCalendar $holidays,
    ) {}

    /**
     * @param  bool  $halfDay  Only meaningful for a single-day request.
     */
    public function calculate(
        Employee $employee,
        string|\DateTimeInterface $startDate,
        string|\DateTimeInterface $endDate,
        bool $halfDay = false,
    ): CalculatedLeave {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            throw new InvalidArgumentException('Leave end date cannot be before the start date.');
        }

        if ($halfDay && ! $start->isSameDay($end)) {
            throw new InvalidArgumentException('A half day can only be applied to a single-day request.');
        }

        $days = [];

        foreach (CarbonPeriod::create($start, $end) as $date) {
            /** @var Carbon $date */
            $isRestDay = $this->patterns->isRestDay($employee, $date);
            $holidayName = $this->holidays->nameFor($date);

            // A rest day is checked first: if the employee was not going to work
            // that day anyway, the reason it is not deducted is the rest day, not
            // the holiday that happens to fall on it.
            if ($isRestDay) {
                $reason = 'rest_day';
                $label = null;
            } elseif ($holidayName !== null) {
                $reason = 'public_holiday';
                $label = $holidayName;
            } else {
                $reason = null;
                $label = null;
            }

            $days[] = [
                'date' => $date->format('Y-m-d'),
                'year' => (int) $date->format('Y'),
                'fraction' => $halfDay ? 0.5 : 1.0,
                'is_deducted' => $reason === null,
                'exclusion_reason' => $reason,
                'label' => $label,
            ];
        }

        return new CalculatedLeave($days);
    }
}
