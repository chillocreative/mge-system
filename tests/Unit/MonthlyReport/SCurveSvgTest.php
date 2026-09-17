<?php

namespace Tests\Unit\MonthlyReport;

use App\Services\MonthlyReport\Charts\SCurveSvg;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('niceScaleProvider')]
    public function test_nice_scale_computes_step_and_max(float $max, float $expectedStep, float $expectedMax): void
    {
        $scale = SCurveSvg::niceScale($max);

        $this->assertEqualsWithDelta($expectedStep, $scale['step'], 0.01);
        $this->assertEqualsWithDelta($expectedMax, $scale['max'], 0.01);
    }

    public static function niceScaleProvider(): array
    {
        return [
            '288,000,000 -> step 100M, max 300M' => [288_000_000, 100_000_000, 300_000_000],
            '13,000,000 -> step 5M, max 15M' => [13_000_000, 5_000_000, 15_000_000],
            '62,000,000 -> step 20M, max 80M' => [62_000_000, 20_000_000, 80_000_000],
            '950,000 -> step 250k, max 1.0M' => [950_000, 250_000, 1_000_000],
        ];
    }

    public function test_polyline_points_are_locale_independent(): void
    {
        $previous = setlocale(LC_NUMERIC, 0);
        $locale = setlocale(LC_NUMERIC, 'de_DE.UTF-8');

        if ($locale === false) {
            $this->markTestSkipped('de_DE.UTF-8 locale is not available on this system.');
        }

        try {
            $svg = (new SCurveSvg)->render($this->series, ['title' => 'Physical S-Curve']);

            $this->assertMatchesRegularExpression('/<polyline[^>]*points="([^"]*)"/', $svg);
            preg_match_all('/<polyline[^>]*points="([^"]*)"/', $svg, $matches);

            foreach ($matches[1] as $pointsAttr) {
                // Each coordinate pair is "x,y" (single comma as the pair separator);
                // fail if any coordinate itself contains a comma decimal separator.
                foreach (explode(' ', $pointsAttr) as $pair) {
                    $this->assertSame(1, substr_count($pair, ','), "Unexpected comma count in coordinate pair: {$pair}");
                    foreach (explode(',', $pair) as $coord) {
                        $this->assertMatchesRegularExpression('/^-?\d+\.\d+$/', $coord);
                    }
                }
            }
        } finally {
            setlocale(LC_NUMERIC, $previous);
        }
    }
}
