<?php

namespace Tests\Unit;

use App\Services\ReportData\ProgressService;
use PHPUnit\Framework\TestCase;

class ProgressServiceTest extends TestCase
{
    public function test_variance_and_ahead_days_are_derived(): void
    {
        $out = (new ProgressService)->compute([
            'planning_days_completion' => 497,
            'physical_scheduled_pct' => 2,
            'physical_actual_pct' => 4,
            'financial_scheduled_pct' => 15,
            'financial_actual_pct' => 13,
            'ahead_delay_days' => null,
            'physical_status' => null,
        ]);

        $this->assertSame(2.0, $out['physical_variance']);
        $this->assertSame(-2.0, $out['financial_variance']);
        $this->assertSame(10, $out['ahead_delay_days']);   // round(2/100 * 497)
        $this->assertSame('AHEAD', $out['physical_status']);
    }

    public function test_status_on_track_when_variance_zero_and_overrides_are_kept(): void
    {
        $out = (new ProgressService)->compute([
            'planning_days_completion' => 497,
            'physical_scheduled_pct' => 1,
            'physical_actual_pct' => 1,
            'financial_scheduled_pct' => 13,
            'financial_actual_pct' => 0,
            'ahead_delay_days' => 3,
            'physical_status' => 'DELAY',
        ]);

        $this->assertSame(0.0, $out['physical_variance']);
        $this->assertSame(3, $out['ahead_delay_days']);
        $this->assertSame('DELAY', $out['physical_status']);
    }
}
