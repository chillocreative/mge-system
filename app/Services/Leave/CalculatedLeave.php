<?php

namespace App\Services\Leave;

/**
 * The result of calculating a leave request: one entry per calendar day in the
 * requested range, each marked as deducted or excluded with a reason.
 *
 * This is what gets persisted to leave_days (plan 27.13) and what the apply form
 * renders as its breakdown. Keeping the per-day detail rather than just a total
 * is the whole point: it answers "why is mine 5 days and his 4?" directly from
 * stored data, instead of re-deriving it and hoping the derivation still matches.
 */
class CalculatedLeave
{
    /**
     * @param  array<int, array{date: string, year: int, fraction: float, is_deducted: bool, exclusion_reason: ?string, label: ?string}>  $days
     */
    public function __construct(public readonly array $days) {}

    /**
     * Total days actually deducted from the balance.
     */
    public function totalDeducted(): float
    {
        return round(array_sum(array_map(
            fn (array $d) => $d['is_deducted'] ? $d['fraction'] : 0.0,
            $this->days,
        )), 2);
    }

    /**
     * Deducted days split by leave year — a request spanning 30 Dec to 3 Jan
     * belongs to two years, and each year's balance must take only its share
     * (plan AB4).
     *
     * @return array<int, float>
     */
    public function deductedByYear(): array
    {
        $totals = [];

        foreach ($this->days as $day) {
            if (! $day['is_deducted']) {
                continue;
            }

            $totals[$day['year']] = round(($totals[$day['year']] ?? 0.0) + $day['fraction'], 2);
        }

        ksort($totals);

        return $totals;
    }

    public function calendarDays(): int
    {
        return count($this->days);
    }

    /**
     * @return array<int, array{date: string, reason: string, label: ?string}>
     */
    public function exclusions(): array
    {
        return array_values(array_map(
            fn (array $d) => [
                'date' => $d['date'],
                'reason' => $d['exclusion_reason'],
                'label' => $d['label'],
            ],
            array_filter($this->days, fn (array $d) => ! $d['is_deducted']),
        ));
    }

    public function spansMultipleYears(): bool
    {
        return count($this->deductedByYear()) > 1;
    }
}
