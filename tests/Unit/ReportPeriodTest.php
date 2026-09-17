<?php

namespace Tests\Unit;

use App\Support\ReportPeriod;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ReportPeriodTest extends TestCase
{
    public function test_period_for_month_runs_from_day_after_cutoff_of_previous_month_to_cutoff(): void
    {
        $p = ReportPeriod::forMonth(15, 2026, 1);

        $this->assertSame('2025-12-16', $p['start']->toDateString());
        $this->assertSame('2026-01-15', $p['end']->toDateString());
    }

    public function test_cutoff_31_clamps_to_month_length(): void
    {
        $p = ReportPeriod::forMonth(31, 2026, 2);

        $this->assertSame('2026-02-01', $p['start']->toDateString());
        $this->assertSame('2026-02-28', $p['end']->toDateString());
    }

    public function test_containing_returns_the_period_a_date_falls_in(): void
    {
        $p = ReportPeriod::containing(15, Carbon::parse('2026-01-20'));

        $this->assertSame('2026-01-16', $p['start']->toDateString());
        $this->assertSame('2026-02-15', $p['end']->toDateString());
    }
}
