<?php

namespace App\Services\MonthlyReport\Export\Docx\Writers;

use App\Services\MonthlyReport\Export\Docx\DocxDocument;
use Illuminate\Support\Facades\Storage;

/**
 * Renders 2.2 PHYSICAL S-CURVE / 2.4 FINANCIAL S-CURVE: the latest captured chart PNG asset
 * (embedded — the view data's chart_svg_uri is SVG, which Word cannot render), or a fallback
 * paragraph when none was captured, followed by the month series table(s) chunked like the PDF
 * (s2-2.blade.php / s2-4.blade.php).
 */
final class ChartWriter implements SectionWriter
{
    /** @var array<string, string> */
    private const ASSET_KIND = [
        '2.2' => 'chart_physical_scurve',
        '2.4' => 'chart_financial_scurve',
    ];

    public function write(DocxDocument $doc, string $key, array $data, ?string $note, array $ctx): void
    {
        $months = $data['series']['months'] ?? [];

        if (empty($months)) {
            $doc->paragraph('No data.', ['italic' => true, 'color' => '555555']);

            return;
        }

        $isLandscape = ($ctx['orientation'] ?? 'portrait') === 'landscape';

        $this->writeChartImage($doc, $key, $ctx, $isLandscape);

        if ($key === '2.4') {
            $this->writeFinancialTables($doc, $data['series'], $isLandscape);

            return;
        }

        $this->writePhysicalTables($doc, $data['series'], $isLandscape);
    }

    private function writeChartImage(DocxDocument $doc, string $key, array $ctx, bool $isLandscape): void
    {
        $kind = self::ASSET_KIND[$key] ?? null;
        $report = $ctx['report'] ?? null;
        $binary = null;

        if ($kind && $report) {
            $asset = $report->assets()->where('kind', $kind)->latest('id')->first();
            if ($asset && Storage::disk('local')->exists($asset->file_path)) {
                $binary = Storage::disk('local')->get($asset->file_path);
            }
        }

        if ($binary === null) {
            $doc->paragraph(
                'Chart not captured — open the report in the editor and export again to embed the chart.',
                ['italic' => true, 'color' => '555555']
            );

            return;
        }

        $doc->image($binary, ['width' => $isLandscape ? 170 : 160]);
    }

    private function writePhysicalTables(DocxDocument $doc, array $series, bool $isLandscape): void
    {
        $months = $series['months'] ?? [];
        $scheduled = $series['scheduled'] ?? [];
        $actual = $series['actual'] ?? [];
        $chunkSize = $isLandscape ? 13 : 12;

        foreach (array_chunk(array_keys($months), $chunkSize) as $chunk) {
            $headers = array_merge(['Month'], array_map(fn ($i) => $months[$i], $chunk));
            $rows = [
                array_merge(['Scheduled (%)'], array_map(fn ($i) => $scheduled[$i] ?? '-', $chunk)),
                array_merge(['Actual (%)'], array_map(fn ($i) => $actual[$i] ?? '-', $chunk)),
            ];

            $doc->table($headers, $rows, ['fontSize' => 8]);
        }
    }

    private function writeFinancialTables(DocxDocument $doc, array $series, bool $isLandscape): void
    {
        $months = $series['months'] ?? [];
        $scheduled = $series['scheduled'] ?? [];
        $scheduledPct = $series['scheduled_pct'] ?? [];
        $actual = $series['actual'] ?? [];
        $actualPct = $series['actual_pct'] ?? [];
        // Matches s2-4.blade.php's $chunks (financial has 5 data rows vs 2.2's 2, so it fits
        // fewer months per portrait page: 6 here vs 12 in writePhysicalTables()).
        $chunkSize = $isLandscape ? 13 : 6;

        foreach (array_chunk(array_keys($months), $chunkSize) as $chunk) {
            $headers = array_merge(['Month'], array_map(fn ($i) => $months[$i], $chunk));
            $rows = [
                array_merge(['Scheduled (RM)'], array_map(fn ($i) => isset($scheduled[$i]) ? number_format($scheduled[$i], 2) : '-', $chunk)),
                array_merge(['Scheduled (%)'], array_map(fn ($i) => $scheduledPct[$i] ?? '-', $chunk)),
                array_merge(['Actual (RM)'], array_map(fn ($i) => isset($actual[$i]) && $actual[$i] !== null ? number_format($actual[$i], 2) : '-', $chunk)),
                array_merge(['Actual (%)'], array_map(fn ($i) => $actualPct[$i] ?? '-', $chunk)),
            ];

            $doc->table($headers, $rows, ['fontSize' => 8]);
        }
    }
}
