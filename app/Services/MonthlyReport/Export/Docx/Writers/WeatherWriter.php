<?php

namespace App\Services\MonthlyReport\Export\Docx\Writers;

use App\Services\MonthlyReport\Export\Docx\DocxDocument;
use Illuminate\Support\Carbon;

/**
 * Renders 4.3 WEATHER REPORT: a Date + 24 hour-column + Hrs grid, shading each hour cell
 * '60A5FA' when it overlaps a raining interval, then the total/raining days/hours summary —
 * mirrors s4-3.blade.php.
 */
final class WeatherWriter implements SectionWriter
{
    private const RAIN_FILL = '60A5FA';

    public function write(DocxDocument $doc, string $key, array $data, ?string $note, array $ctx): void
    {
        $days = $data['days'] ?? [];

        if (empty($days)) {
            $doc->paragraph('No data.', ['italic' => true, 'color' => '555555']);

            return;
        }

        $headers = array_merge(['Date'], array_map(fn ($h) => (string) $h, range(0, 23)), ['Hrs']);

        $rows = [];
        foreach ($days as $day) {
            $intervals = $day['intervals'] ?? [];
            $hrs = round(array_sum(array_map(
                fn ($i) => max(0, min($i[1], 1440) - max($i[0], 0)),
                $intervals
            )) / 60, 1);

            $row = [Carbon::parse($day['date'])->format('d/m')];
            foreach (range(0, 23) as $h) {
                $row[] = '';
            }
            $row[] = $hrs > 0 ? $hrs : '-';
            $rows[] = $row;
        }

        $doc->table($headers, $rows, [
            'fontSize' => 6,
            'align' => array_fill(0, 26, 'c'),
            'shading' => function ($r, $c) use ($days) {
                if ($c < 1 || $c > 24) {
                    return null;
                }

                $h = $c - 1;
                $intervals = $days[$r]['intervals'] ?? [];
                foreach ($intervals as [$s, $e]) {
                    if ($s < ($h + 1) * 60 && $e > $h * 60) {
                        return self::RAIN_FILL;
                    }
                }

                return null;
            },
        ]);

        $doc->keyValue([
            'Total days' => $data['summary']['total_days'] ?? count($days),
            'Raining days' => $data['summary']['raining_days'] ?? '-',
            'Raining hours' => $data['summary']['raining_hours'] ?? '-',
        ]);
    }
}
