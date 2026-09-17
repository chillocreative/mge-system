<?php

namespace Tests\Unit\MonthlyReport;

use App\Services\MonthlyReport\Charts\SCurveSvg;
use PHPUnit\Framework\TestCase;

class SCurveSvgTest extends TestCase
{
    private array $series = [
        'months' => ['Oct-25', 'Nov-25', 'Dec-25', 'Jan-26'],
        'scheduled' => [0, 10, 30, 60],
        'actual' => [null, 8, 25, null],
    ];

    public function test_renders_two_polylines_and_skips_null_actuals(): void
    {
        $svg = (new SCurveSvg)->render($this->series, ['title' => 'Physical S-Curve']);

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertSame(2, substr_count($svg, '<polyline'));
        $this->assertSame(2, substr_count($svg, '<circle')); // markers only for the two non-null actuals
        $this->assertStringContainsString('Physical S-Curve', $svg);
        $this->assertStringContainsString('Jan-26', $svg);
        $this->assertStringContainsString('100%', $svg);
    }

    public function test_ringgit_axis_uses_nice_max_and_millions(): void
    {
        $svg = (new SCurveSvg)->render([
            'months' => ['Oct-25', 'Nov-25'],
            'scheduled' => [12470400, 288000000],
            'actual' => [null, 11520000],
        ], ['unit' => 'RM']);

        $this->assertStringContainsString('RM 300.0M', $svg);
        $this->assertStringContainsString('RM 0', $svg);
    }

    public function test_escapes_labels_and_yields_data_uri(): void
    {
        $svg = (new SCurveSvg)->render(['months' => ['A&B'], 'scheduled' => [1], 'actual' => [null]]);
        $this->assertStringContainsString('A&amp;B', $svg);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', SCurveSvg::dataUri($svg));
    }

    public function test_handles_empty_series_without_errors(): void
    {
        $svg = (new SCurveSvg)->render(['months' => [], 'scheduled' => [], 'actual' => []]);
        $this->assertStringContainsString('No data', $svg);
    }
}
