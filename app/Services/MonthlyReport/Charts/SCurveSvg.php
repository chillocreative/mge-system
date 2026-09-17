<?php

namespace App\Services\MonthlyReport\Charts;

/**
 * Renders a Physical (%) or Financial (RM) S-curve as a standalone SVG
 * document, suitable for embedding into a PDF via a base64 data URI
 * (DomPDF does not render inline <svg>, only <img src="data:image/svg+xml;base64,...">).
 */
class SCurveSvg
{
    private const LEFT = 90;

    private const RIGHT = 30;

    private const TOP = 40;

    private const BOTTOM = 80;

    private const SCHEDULED_COLOR = '#1d4ed8';

    private const ACTUAL_COLOR = '#dc2626';

    private const GRID_COLOR = '#e5e7eb';

    private const AXIS_COLOR = '#6b7280';

    private const FONT = 'DejaVu Sans';

    /**
     * @param  array{months?:array<int,string>,scheduled?:array<int,mixed>,actual?:array<int,mixed>}  $series
     * @param  array{title?:string,unit?:string,y_max?:float,width?:int,height?:int}  $opts
     */
    public function render(array $series, array $opts = []): string
    {
        $width = (int) ($opts['width'] ?? 1000);
        $height = (int) ($opts['height'] ?? 480);
        $title = (string) ($opts['title'] ?? '');
        $unit = (string) ($opts['unit'] ?? '%');

        $months = array_values($series['months'] ?? []);
        $scheduled = $this->normalizeValues($series['scheduled'] ?? []);
        $actual = $this->normalizeValues($series['actual'] ?? []);

        $canvas = new SvgCanvas;

        if (count($months) === 0) {
            $canvas->text($width / 2, $height / 2, 'No data', [
                'font-family' => self::FONT,
                'font-size' => 14,
                'fill' => self::AXIS_COLOR,
                'text-anchor' => 'middle',
            ]);

            return $canvas->toString($width, $height);
        }

        $plotLeft = self::LEFT;
        $plotRight = $width - self::RIGHT;
        $plotTop = self::TOP;
        $plotBottom = $height - self::BOTTOM;
        $plotWidth = $plotRight - $plotLeft;
        $plotHeight = $plotBottom - $plotTop;

        $isPercent = $unit !== 'RM';
        $yMax = isset($opts['y_max'])
            ? (float) $opts['y_max']
            : ($isPercent ? 100.0 : $this->niceMax(max(array_merge($scheduled, $actual, [0]))));

        if ($yMax <= 0) {
            $yMax = $isPercent ? 100.0 : 1.0;
        }

        // Background.
        $canvas->rect(0, 0, $width, $height, ['fill' => '#ffffff']);

        // Title.
        if ($title !== '') {
            $canvas->text($width / 2, 24, $title, [
                'font-family' => self::FONT,
                'font-size' => 12,
                'font-weight' => 'bold',
                'fill' => '#111827',
                'text-anchor' => 'middle',
            ]);
        }

        // Horizontal gridlines + Y labels.
        $gridLines = 5;
        for ($i = 0; $i < $gridLines; $i++) {
            $fraction = $i / ($gridLines - 1);
            $value = $yMax * $fraction;
            $y = $plotBottom - $fraction * $plotHeight;

            $canvas->line($plotLeft, $y, $plotRight, $y, [
                'stroke' => self::GRID_COLOR,
                'stroke-width' => 1,
            ]);

            $label = $isPercent ? $this->fmtPercent($value) : $this->fmtRm($value);

            $canvas->text($plotLeft - 8, $y + 3, $label, [
                'font-family' => self::FONT,
                'font-size' => 9,
                'fill' => self::AXIS_COLOR,
                'text-anchor' => 'end',
            ]);
        }

        // Axis lines.
        $canvas->line($plotLeft, $plotTop, $plotLeft, $plotBottom, ['stroke' => self::AXIS_COLOR, 'stroke-width' => 1]);
        $canvas->line($plotLeft, $plotBottom, $plotRight, $plotBottom, ['stroke' => self::AXIS_COLOR, 'stroke-width' => 1]);

        // X positions.
        $count = count($months);
        $xPositions = [];
        for ($i = 0; $i < $count; $i++) {
            $xPositions[] = $count > 1
                ? $plotLeft + ($plotWidth * $i / ($count - 1))
                : $plotLeft + $plotWidth / 2;
        }

        // X labels, thinned when > 18 months.
        $thinFactor = $count > 18 ? 2 : 1;
        foreach ($months as $i => $month) {
            if ($i % $thinFactor !== 0) {
                continue;
            }

            $x = $xPositions[$i];
            $canvas->text($x, $plotBottom + 14, (string) $month, [
                'font-family' => self::FONT,
                'font-size' => 9,
                'fill' => self::AXIS_COLOR,
                'text-anchor' => 'end',
                'transform' => sprintf('rotate(-45 %s %s)', $x, $plotBottom + 14),
            ]);
        }

        // Scheduled line.
        $scheduledPoints = [];
        foreach ($scheduled as $i => $value) {
            if ($value === null || ! isset($xPositions[$i])) {
                continue;
            }
            $y = $plotBottom - min($value, $yMax) / $yMax * $plotHeight;
            $scheduledPoints[] = [$xPositions[$i], $y];
        }
        if (count($scheduledPoints) >= 2) {
            $canvas->polyline($scheduledPoints, self::SCHEDULED_COLOR, 2.2);
        }

        // Actual line — through non-null points only, with markers.
        $actualPoints = [];
        foreach ($actual as $i => $value) {
            if ($value === null || ! isset($xPositions[$i])) {
                continue;
            }
            $y = $plotBottom - min($value, $yMax) / $yMax * $plotHeight;
            $actualPoints[] = [$xPositions[$i], $y];
        }
        if (count($actualPoints) >= 2) {
            $canvas->polyline($actualPoints, self::ACTUAL_COLOR, 2.2);
        }
        foreach ($actualPoints as $point) {
            $canvas->circle($point[0], $point[1], 3, [
                'fill' => self::ACTUAL_COLOR,
                'stroke' => '#ffffff',
                'stroke-width' => 0.5,
            ]);
        }

        // Legend, top-left of the plot area.
        $legendX = $plotLeft + 6;
        $legendY = $plotTop + 10;
        $canvas->rect($legendX - 4, $legendY - 10, 100, 34, [
            'fill' => '#ffffff',
            'stroke' => self::GRID_COLOR,
            'stroke-width' => 1,
        ]);
        $canvas->line($legendX, $legendY, $legendX + 16, $legendY, ['stroke' => self::SCHEDULED_COLOR, 'stroke-width' => 2.2]);
        $canvas->text($legendX + 20, $legendY + 3, 'Scheduled', [
            'font-family' => self::FONT,
            'font-size' => 9,
            'fill' => '#111827',
        ]);
        $canvas->line($legendX, $legendY + 16, $legendX + 16, $legendY + 16, ['stroke' => self::ACTUAL_COLOR, 'stroke-width' => 2.2]);
        $canvas->text($legendX + 20, $legendY + 19, 'Actual', [
            'font-family' => self::FONT,
            'font-size' => 9,
            'fill' => '#111827',
        ]);

        return $canvas->toString($width, $height);
    }

    public static function dataUri(string $svg): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * @param  array<int,mixed>  $values
     * @return array<int,float|null>
     */
    private function normalizeValues(array $values): array
    {
        return array_map(
            static fn ($v) => $v === null ? null : (float) $v,
            $values
        );
    }

    private function fmtPercent(float $value): string
    {
        return number_format($value, 0).'%';
    }

    private function fmtRm(float $value): string
    {
        if ($value >= 1_000_000) {
            return 'RM '.number_format($value / 1_000_000, 1).'M';
        }

        if ($value >= 1_000) {
            return 'RM '.number_format($value / 1_000, 1).'k';
        }

        return 'RM '.number_format($value);
    }

    /**
     * Ceil `$max` to the next whole unit at its own order of magnitude,
     * e.g. 288,000,000 -> 300,000,000.
     */
    private function niceMax(float $max): float
    {
        if ($max <= 0) {
            return 1.0;
        }

        $magnitude = 10 ** floor(log10($max));

        return ceil($max / $magnitude) * $magnitude;
    }
}
