<?php

namespace App\Support;

use Carbon\Carbon;

class ReportPeriod
{
    /** @return array{start: Carbon, end: Carbon} */
    public static function forMonth(int $cutoffDay, int $year, int $month): array
    {
        $end = Carbon::create($year, $month, 1)->endOfMonth();
        $end = $end->copy()->day(min($cutoffDay, $end->day))->startOfDay();

        $prevMonthEnd = Carbon::create($year, $month, 1)->subMonth()->endOfMonth();
        $start = $prevMonthEnd->copy()->day(min($cutoffDay, $prevMonthEnd->day))->addDay()->startOfDay();

        return ['start' => $start, 'end' => $end];
    }

    /** @return array{start: Carbon, end: Carbon} */
    public static function containing(int $cutoffDay, Carbon $date): array
    {
        $candidate = self::forMonth($cutoffDay, $date->year, $date->month);
        if ($date->gt($candidate['end'])) {
            $next = $date->copy()->addMonthNoOverflow();
            $candidate = self::forMonth($cutoffDay, $next->year, $next->month);
        }

        return $candidate;
    }
}
