<?php

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\WorkPattern;
use Illuminate\Support\Collection;

/**
 * Resolves which days of the week an employee actually works (plan 27.6b).
 *
 * Work at MGE runs seven days a week — some staff are on site while others are
 * off — so there is no company-wide weekend. A rule like "never count Saturday
 * and Sunday" would be wrong for site crews, and wrong in the direction that
 * costs the company days.
 *
 * Resolution order:
 *   1. the employee's own work_pattern_id, if set (an explicit override)
 *   2. the default pattern for their category (office / site)
 *   3. every day is a working day
 *
 * Step 3 is deliberate. If patterns have not been configured, the calculator
 * deducts MORE days rather than fewer. An over-deduction is visible and gets
 * reported; a silent under-deduction is discovered at year end when someone has
 * already taken leave they did not have.
 */
class WorkPatternResolver
{
    /** @var Collection<int, WorkPattern> */
    private Collection $patterns;

    public function __construct(?Collection $patterns = null)
    {
        $this->patterns = $patterns ?? WorkPattern::active()->get();
    }

    /**
     * ISO-8601 weekday numbers the employee works: 1 = Monday ... 7 = Sunday.
     *
     * @return array<int, int>
     */
    public function workingDaysFor(Employee $employee): array
    {
        $pattern = $this->patternFor($employee);

        if ($pattern === null || empty($pattern->working_days)) {
            return [1, 2, 3, 4, 5, 6, 7];
        }

        return array_map('intval', $pattern->working_days);
    }

    public function patternFor(Employee $employee): ?WorkPattern
    {
        if ($employee->work_pattern_id !== null) {
            $own = $this->patterns->firstWhere('id', $employee->work_pattern_id);

            if ($own !== null) {
                return $own;
            }
        }

        if ($employee->category === null) {
            return null;
        }

        return $this->patterns->first(
            fn (WorkPattern $p) => $p->is_default_for !== null
                && strcasecmp($p->is_default_for, $employee->category) === 0,
        );
    }

    /**
     * Is this a rest day for the employee?
     */
    public function isRestDay(Employee $employee, \DateTimeInterface $date): bool
    {
        return ! in_array(
            (int) $date->format('N'),
            $this->workingDaysFor($employee),
            true,
        );
    }
}
