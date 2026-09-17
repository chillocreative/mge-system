<?php

namespace Tests\Unit\MonthlyReport;

use App\Services\MonthlyReport\Export\OrientationPlanner;
use PHPUnit\Framework\TestCase;

class OrientationPlannerTest extends TestCase
{
    public function test_groups_consecutive_sections_by_orientation_with_defaults(): void
    {
        $chunks = OrientationPlanner::plan(['cover', '1.1', '2.1', '2.2', '2.3', '2.4', '2.6', '4.1', '4.2', '5.0'], null);

        $this->assertSame([
            ['orientation' => 'portrait', 'keys' => ['cover', '1.1', '2.1']],
            ['orientation' => 'landscape', 'keys' => ['2.2', '2.3', '2.4']],
            ['orientation' => 'portrait', 'keys' => ['2.6']],
            ['orientation' => 'landscape', 'keys' => ['4.1', '4.2']],
            ['orientation' => 'portrait', 'keys' => ['5.0']],
        ], $chunks);
    }

    public function test_override_replaces_defaults_and_cover_is_always_portrait(): void
    {
        $chunks = OrientationPlanner::plan(['cover', '1.1', '2.2'], ['1.1', 'cover']);

        $this->assertSame([
            ['orientation' => 'portrait', 'keys' => ['cover']],
            ['orientation' => 'landscape', 'keys' => ['1.1']],
            ['orientation' => 'portrait', 'keys' => ['2.2']],
        ], $chunks);
    }
}
