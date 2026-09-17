# Monthly Progress Report — Phase C: Charts & Layout Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The PDF export matches the reference layout: S-curve charts in 2.2/2.4, a 24-hour weather grid in 4.3, landscape pages for the wide sections, user-uploaded Gantt pages appended after 2.5, and global "Page X of Y" numbering across the merged document.

**Architecture:** Charts are generated **server-side as SVG** (pure PHP string builders under `app/Services/MonthlyReport/Charts/`) and embedded in Blade as `<img src="data:image/svg+xml;base64,…">` — verified to render in DomPDF 3.1 (lines, dashes, markers, rotated text) with no GD/Imagick. The exporter splits the section list into consecutive **orientation chunks** (portrait/landscape), renders each chunk with DomPDF, then a `PdfMerger` (setasign/fpdi + fpdf, pure PHP) concatenates chunks, appends uploaded Gantt PDF pages / images, and stamps the footer ("Monthly Progress Report No.N" left, "Page X of Y" right) on every page. Gantt pages are `monthly_report_assets` rows (kind `gantt_page`) uploaded through a new assets API.

**Tech Stack:** Laravel 12, DomPDF 3.1 (+ php-svg-lib), setasign/fpdi 2.x + setasign/fpdf 1.8 (new, committed to `vendor/`), React 19, PHPUnit, Pint.

**Spec:** `docs/superpowers/specs/2026-09-17-monthly-progress-report-design.md` — §3.12, §3.14 (assets), §4 (Charts/, Export/), §5.1, §6 (assets routes), §7 (chart sections), §8 Phase C.

## Global Constraints

- Production PHP lacks `fileinfo` → validate uploads with `extensions:` only; derive MIME from the extension map; never `Storage::response()`/`mimes:`; do not call `finfo`.
- GD may be absent on production → charts are SVG generated in PHP; no image conversion. GD may be used only behind `function_exists('imagecreatefromstring')`.
- `vendor/` is committed and production never runs composer → new packages are committed with the code (`composer require`, then commit `composer.json`, `composer.lock`, `vendor/setasign/**`, `vendor/composer/**` autoload files).
- `public/build/**` is committed and production never runs vite → every `resources/js` change ships with `npm run build` output in the same commit.
- Authorization is permission-based via route middleware (`reports.view` for export/read, `reports.manage` for writes); nested resources are scoped by `report_id` (asset must belong to the report in the URL) — 404 otherwise.
- Finalised reports reject every write (asset upload/delete/reorder, options) with 422 — same `ValidationException` style as `MonthlyReportService::saveSection`.
- No destructive DB commands on the local database (`migrate:fresh/refresh/rollback`, `db:wipe`). New migrations must be additive and re-runnable-safe on production (`migrate --force`).
- Spec deviation (ruling 2026-09-19): the client-side PNG snapshot / "Refresh charts" flow from §4 is **deferred to Phase D** (only DOCX needs PNG). Phase C charts render automatically on every export with no user action.
- Free FPDI parser cannot import PDFs that use object/xref streams (typically PDF ≥ 1.5 with compression). Uploads are validated at upload time by attempting `setSourceFile` + `importPage(1)`; failures return 422 with the message: `This PDF uses a compression the report merger cannot read. Re-save it as PDF 1.4 (Print to PDF) or upload PNG/JPG pages instead.`
- Lint/test commands: `php vendor/bin/pint --test`, `php -d memory_limit=2G vendor/bin/phpunit`, `npm run build`.
- Commit locally on `feature/monthly-report-phase-c` (from `main` @ d1fe8d3); **no push** until the user approves after local testing.

---

## File structure

| Path | Responsibility |
|---|---|
| `app/Services/MonthlyReport/Charts/SvgCanvas.php` | Tiny SVG string builder (line, polyline, rect, text, dashed) — no logic about charts |
| `app/Services/MonthlyReport/Charts/SCurveSvg.php` | Builds the S-curve SVG from `{months, scheduled, actual}` series (+ optional `unit` and `actual_pct`) |
| `app/Services/MonthlyReport/Charts/WeatherGridHtml.php` | Not needed — 4.3 grid is a Blade table (see Task 3) |
| `app/Services/MonthlyReport/Export/PdfMerger.php` | Merge DomPDF chunk PDFs + appended asset pages, stamp footer, return bytes |
| `app/Services/MonthlyReport/Export/PdfExporter.php` | Orchestrates chunks (orientation), asset appends, hands to merger; keeps `html()` for tests |
| `app/Services/MonthlyReport/Export/OrientationPlanner.php` | Pure function: ordered sections → list of `['orientation' => 'portrait'|'landscape', 'keys' => [...] , 'append_assets_after' => bool]` |
| `app/Services/MonthlyReport/AssetService.php` | store/validate/delete/reorder `monthly_report_assets` files |
| `app/Http/Controllers/Api/MonthlyReportAssetController.php` | `index/store/update/destroy` for assets |
| `resources/views/pdf/monthly-report/layout.blade.php` | Gains `$orientation` (`@page` size) and drops its own page numbers |
| `resources/views/pdf/monthly-report/sections/s2-2.blade.php`, `s2-4.blade.php` | Chart image + existing tables |
| `resources/views/pdf/monthly-report/sections/s4-3.blade.php` | 24-hour grid table + totals |
| `resources/views/pdf/monthly-report/sections/s4-1.blade.php`, `s4-2.blade.php` | Landscape: full-period single table when `$orientation === 'landscape'` |
| `resources/views/pdf/monthly-report/asset-image.blade.php` | Full-page landscape image page for PNG/JPG Gantt pages |
| `resources/js/services/monthlyReportService.js` | `listAssets/uploadAsset/updateAsset/deleteAsset/getChartUrl` |
| `resources/js/pages/reports/editor/ChartSection.jsx` | 2.2/2.4: server SVG preview + existing series table editor |
| `resources/js/pages/reports/editor/GanttAssetsPanel.jsx` | 2.5: upload/list/reorder/delete Gantt pages |
| `resources/js/pages/reports/editor/ReportOptionsDialog.jsx` | Landscape sections multi-select (`options.landscape_sections`) |
| `tests/Unit/MonthlyReport/SCurveSvgTest.php`, `OrientationPlannerTest.php`, `tests/Feature/MonthlyReport/PdfMergeTest.php`, `AssetApiTest.php` | Tests |

---

### Task 1: FPDI dependency + PdfMerger

**Files:**
- Modify: `composer.json`, `composer.lock`, `vendor/**` (committed)
- Create: `app/Services/MonthlyReport/Export/PdfMerger.php`
- Test: `tests/Feature/MonthlyReport/PdfMergeTest.php`

**Interfaces:**
- Produces: `PdfMerger::merge(array $parts, string $footerLeft): string` where each part is `['pdf' => string $bytes]` (a DomPDF output) or `['file' => string $absolutePath]` (an uploaded PDF, all pages imported) — returns the merged PDF bytes with the footer stamped on every page: left text `$footerLeft` at 8pt Helvetica, right text `Page X of Y`, both 10 mm from the bottom edge, honouring each page's own size/orientation.
- Produces: `PdfMerger::probe(string $absolutePath): int` — returns the page count if FPDI can import the file, throws `\RuntimeException` with the Global-Constraints message otherwise.

- [ ] **Step 1: Install packages**

```bash
composer require setasign/fpdi:^2.6 setasign/fpdf:^1.8
git add composer.json composer.lock vendor/setasign vendor/composer
```
Expected: `vendor/setasign/fpdi` and `vendor/setasign/fpdf` exist; `composer show setasign/fpdi` prints 2.6.x.

- [ ] **Step 2: Write the failing test**

```php
<?php
namespace Tests\Feature\MonthlyReport;

use App\Services\MonthlyReport\Export\PdfMerger;
use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class PdfMergeTest extends TestCase
{
    public function test_merges_portrait_and_landscape_chunks_and_stamps_global_page_numbers(): void
    {
        $a = Pdf::loadHTML('<p>one</p><div style="page-break-before:always">two</div>')->setPaper('a4', 'portrait')->output();
        $b = Pdf::loadHTML('<p>three</p>')->setPaper('a4', 'landscape')->output();

        $bytes = app(PdfMerger::class)->merge([['pdf' => $a], ['pdf' => $b]], 'Monthly Progress Report No.9');

        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertSame(3, preg_match_all('/\/Type\s*\/Page[^s]/', $bytes));
        $this->assertStringContainsString('Page 3 of 3', $this->decodeText($bytes));
        $this->assertStringContainsString('Monthly Progress Report No.9', $this->decodeText($bytes));
    }

    public function test_probe_rejects_unreadable_pdf(): void
    {
        $path = storage_path('app/testing/not-a-pdf.pdf');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, 'hello');

        $this->expectException(\RuntimeException::class);
        app(PdfMerger::class)->probe($path);
    }

    /** FPDF writes text uncompressed in a Tj operator; decode by stripping the stream to visible text. */
    private function decodeText(string $pdf): string
    {
        return implode(' ', array_map(fn ($m) => $m[1], iterator_to_array((function () use ($pdf) {
            preg_match_all('/\((.*?)\)\s*Tj/s', $pdf, $ms, PREG_SET_ORDER);
            yield from $ms;
        })())));
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php -d memory_limit=2G vendor/bin/phpunit tests/Feature/MonthlyReport/PdfMergeTest.php`
Expected: FAIL — class `PdfMerger` not found.

- [ ] **Step 4: Implement `PdfMerger`**

```php
<?php
namespace App\Services\MonthlyReport\Export;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParserException;

class PdfMerger
{
    /** @param array<int, array{pdf?: string, file?: string}> $parts */
    public function merge(array $parts, string $footerLeft): string
    {
        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);
        $pdf->SetCompression(false); // keeps footer text greppable in tests; size impact is small

        $temp = [];
        try {
            foreach ($parts as $part) {
                $path = $part['file'] ?? null;
                if (! $path) {
                    $path = tempnam(sys_get_temp_dir(), 'mpr');
                    file_put_contents($path, $part['pdf']);
                    $temp[] = $path;
                }
                $count = $pdf->setSourceFile($path);
                for ($i = 1; $i <= $count; $i++) {
                    $tpl = $pdf->importPage($i);
                    $size = $pdf->getTemplateSize($tpl);
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($tpl);
                }
            }
        } finally {
            foreach ($temp as $t) { @unlink($t); }
        }

        $total = $pdf->PageNo();
        for ($p = 1; $p <= $total; $p++) {
            $pdf->setPage($p);
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(14, $pdf->GetPageHeight() - 12);
            $pdf->Cell(0, 5, $footerLeft, 0, 0, 'L');
            $pdf->SetXY(-64, $pdf->GetPageHeight() - 12);
            $pdf->Cell(50, 5, "Page {$p} of {$total}", 0, 0, 'R');
        }

        return $pdf->Output('S');
    }

    public function probe(string $absolutePath): int
    {
        try {
            $pdf = new Fpdi();
            $count = $pdf->setSourceFile($absolutePath);
            $pdf->importPage(1);
            return $count;
        } catch (PdfParserException|\Throwable $e) {
            throw new \RuntimeException('This PDF uses a compression the report merger cannot read. Re-save it as PDF 1.4 (Print to PDF) or upload PNG/JPG pages instead.', 0, $e);
        }
    }
}
```
Note: `Fpdi::PageNo()` after the loop returns the current page, which is the last added — use a counter if it does not. `GetPageHeight()` exists in FPDF 1.8.

- [ ] **Step 5: Run test to verify it passes**

Run: `php -d memory_limit=2G vendor/bin/phpunit tests/Feature/MonthlyReport/PdfMergeTest.php`
Expected: PASS (2 tests). If the `Tj` regex fails because FPDI wraps text differently, loosen `decodeText` to search the raw bytes for `(Page 3 of 3)`.

- [ ] **Step 6: Commit**

```bash
php vendor/bin/pint --test
git add composer.json composer.lock vendor/setasign vendor/composer app/Services/MonthlyReport/Export/PdfMerger.php tests/Feature/MonthlyReport/PdfMergeTest.php
git commit -m "Add FPDI-based PDF merger with global page numbering"
```

---

### Task 2: S-curve SVG generator

**Files:**
- Create: `app/Services/MonthlyReport/Charts/SvgCanvas.php`, `app/Services/MonthlyReport/Charts/SCurveSvg.php`
- Test: `tests/Unit/MonthlyReport/SCurveSvgTest.php`

**Interfaces:**
- Consumes: section data `series` as produced by `PhysicalSCurveBuilder` (2.2: `months[]`, `scheduled[]` %, `actual[]` % with `null` for future months) and `FinancialSCurveBuilder` (2.4: `months[]`, `scheduled[]` RM, `actual[]` RM, `actual_pct[]`).
- Produces: `SCurveSvg::render(array $series, array $opts = []): string` — a complete `<svg …>` document, width 1000 × height 480 user units; `$opts`: `title` (string), `unit` (`'%'` default or `'RM'`), `y_max` (auto: 100 for `%`; for RM the max of both series rounded up to a "nice" number), `width`, `height`. Also `SCurveSvg::dataUri(string $svg): string` → `data:image/svg+xml;base64,…`.
- Drawing rules: plot area with 5 horizontal gridlines and Y labels (percent as `0%…100%`; RM as `RM 0`, `RM 50.0M`, using `M` for ≥ 1,000,000 and `k` for ≥ 1,000); X labels = every month label rotated −45° at 9px, thinned to every 2nd label when > 18 months; `scheduled` = solid blue `#1d4ed8` 2.2px polyline; `actual` = solid red `#dc2626` 2.2px polyline through non-null points only, with 3px circle markers; a legend box top-left ("Scheduled", "Actual"); title centred at top (12px bold); `font-family="DejaVu Sans"` everywhere; all text XML-escaped.

- [ ] **Step 1: Write the failing tests**

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php vendor/bin/phpunit tests/Unit/MonthlyReport/SCurveSvgTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `SvgCanvas` and `SCurveSvg`**

`SvgCanvas` — methods `rect`, `line`, `polyline(array $points, string $stroke, float $width, ?string $dash = null)`, `circle`, `text(float $x, float $y, string $s, array $attrs = [])`, `group(string $inner, array $attrs)`, `toString(int $w, int $h)`; every attribute/text value passes through `htmlspecialchars($v, ENT_XML1 | ENT_QUOTES)`.

`SCurveSvg::render` — layout constants: `left=90, right=30, top=40, bottom=80`; `niceMax(float $max)`: for `%` → 100; for RM → ceil to 1/2/5 × 10^n (e.g. 288,000,000 → 300,000,000); Y label formatter `fmtRm($v)`: `>= 1e6` → `'RM '.number_format($v/1e6, 1).'M'`, `>= 1e3` → `…k`, else `'RM '.number_format($v)`; X positions evenly spaced by index; skip a point when its value is `null`; when `months` is empty return an SVG with a centred "No data" text. Return `$canvas->toString($w, $h)`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php vendor/bin/phpunit tests/Unit/MonthlyReport/SCurveSvgTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
php vendor/bin/pint --test
git add app/Services/MonthlyReport/Charts tests/Unit/MonthlyReport/SCurveSvgTest.php
git commit -m "Add server-side S-curve SVG generator"
```

---

### Task 3: Charts and weather grid in the PDF

**Files:**
- Modify: `resources/views/pdf/monthly-report/sections/s2-2.blade.php`, `s2-4.blade.php`, `s4-3.blade.php`, `app/Services/MonthlyReport/Export/PdfExporter.php` (viewData), `app/Services/MonthlyReport/Sections/WeatherBuilder.php` (only if `days[].intervals` is missing from `merged`)
- Test: extend `tests/Feature/MonthlyReport/PdfExportTest.php`

**Interfaces:**
- Consumes: `SCurveSvg::render/dataUri` (Task 2); 2.2/2.4 merged data `series`; 4.3 merged data `days[] = {date, intervals: [[startMin, endMin], …]}` plus totals (`total_days`, `raining_days`, `raining_hours`) as already emitted by `WeatherBuilder`.
- Produces: `PdfExporter::viewData` adds `chart_svg_uri` (string|null) to the 2.2 and 2.4 view data: `SCurveSvg::dataUri(render($merged['series'], ['title' => '2.2 PHYSICAL S-CURVE', 'unit' => '%']))` / `['title' => '2.4 FINANCIAL S-CURVE', 'unit' => 'RM']`; null when `series.months` is empty.

- [ ] **Step 1: Write the failing test**

Add to `PdfExportTest`:
```php
public function test_html_embeds_s_curve_charts_and_weather_grid(): void
{
    $report = $this->makeReportWithSeries(); // helper: report for a project with 3 periods + baseline so 2.2/2.4 have months; and 2 site logs with rain_start 10:00 / rain_stop 12:30 on one day
    $html = app(PdfExporter::class)->html($report);

    $this->assertSame(2, substr_count($html, 'data:image/svg+xml;base64,'));
    $this->assertStringContainsString('class="weather-grid"', $html);
    $this->assertStringContainsString('Raining hours', $html);
    $this->assertStringNotContainsString('Chart available in a later phase', $html);
}
```
Build `makeReportWithSeries()` from the fixtures already used in `ProgressSectionsTest` and `SiteLogSectionsTest` (copy the factory/setup lines, do not import those test classes).

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d memory_limit=2G vendor/bin/phpunit tests/Feature/MonthlyReport/PdfExportTest.php`
Expected: FAIL on the SVG count / weather-grid assertions.

- [ ] **Step 3: Implement**

`s2-2.blade.php` / `s2-4.blade.php`: above the existing tables:
```blade
@if(!empty($chart_svg_uri))
    <img src="{{ $chart_svg_uri }}" style="width: 100%; height: auto; display:block; margin: 0 0 4mm 0;">
@endif
```
Remove the "Chart available in a later phase." line.

`s4-3.blade.php`: replace the day list with the 24-hour grid:
```blade
@php
    $days = $data['days'] ?? [];
    $hours = range(0, 23);
    $filled = function (array $intervals, int $h): bool {
        foreach ($intervals as [$s, $e]) { if ($s < ($h + 1) * 60 && $e > $h * 60) return true; }
        return false;
    };
@endphp
<table class="weather-grid" style="table-layout:fixed; width:100%; border-collapse:collapse; font-size:6.5pt;">
    <thead><tr><th style="width:16mm">Date</th>@foreach($hours as $h)<th>{{ $h }}</th>@endforeach<th style="width:10mm">Hrs</th></tr></thead>
    <tbody>
    @foreach($days as $d)
        @php $hrs = round(array_sum(array_map(fn ($i) => max(0, min($i[1], 1440) - max($i[0], 0)), $d['intervals'] ?? [])) / 60, 1); @endphp
        <tr>
            <td>{{ \Carbon\Carbon::parse($d['date'])->format('d/m') }}</td>
            @foreach($hours as $h)<td style="{{ $filled($d['intervals'] ?? [], $h) ? 'background:#60a5fa;' : '' }}">&nbsp;</td>@endforeach
            <td style="text-align:right">{{ $hrs > 0 ? $hrs : '-' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
<table class="kv" style="margin-top:3mm">
    <tr><td>Total days</td><td>{{ $data['total_days'] ?? count($days) }}</td></tr>
    <tr><td>Raining days</td><td>{{ $data['raining_days'] ?? '-' }}</td></tr>
    <tr><td>Raining hours</td><td>{{ $data['raining_hours'] ?? '-' }}</td></tr>
</table>
```
Keep the key names exactly as `WeatherBuilder` emits them (check the builder; if totals are nested under `totals`, read from there).

`PdfExporter::viewData`: after merging, for keys `2.2`/`2.4` compute `chart_svg_uri` as in Interfaces and pass it into the section view data.

- [ ] **Step 4: Run tests, regenerate the local demo PDF, inspect**

Run: `php -d memory_limit=2G vendor/bin/phpunit tests/Feature/MonthlyReport` → PASS.
Run: `php artisan tinker --execute="file_put_contents('storage/app/mpr-demo-no3.pdf', app(\App\Services\MonthlyReport\Export\PdfExporter::class)->render(\App\Models\MonthlyReport::with(['sections','project','period'])->where('project_id',3)->firstOrFail())->output());"` and check 2.2, 2.4, 4.3 pages visually (charts drawn; grid has blue cells on rain days).

- [ ] **Step 5: Commit**

```bash
php vendor/bin/pint --test
git add resources/views/pdf/monthly-report/sections app/Services/MonthlyReport tests/Feature/MonthlyReport/PdfExportTest.php
git commit -m "Render S-curve charts and the 24-hour weather grid in the report PDF"
```

---

### Task 4: Orientation planner + landscape chunks + merged export

**Files:**
- Create: `app/Services/MonthlyReport/Export/OrientationPlanner.php`
- Modify: `app/Services/MonthlyReport/Export/PdfExporter.php`, `resources/views/pdf/monthly-report/layout.blade.php`, `sections/s4-1.blade.php`, `sections/s4-2.blade.php`, `sections/s2-3.blade.php` (font/width tweak for landscape), `app/Http/Controllers/Api/MonthlyReportController.php` (`update` validates `options.landscape_sections`)
- Test: `tests/Unit/MonthlyReport/OrientationPlannerTest.php`, extend `PdfExportTest`

**Interfaces:**
- Produces: `OrientationPlanner::DEFAULT_LANDSCAPE = ['2.2', '2.3', '2.4', '2.5', '4.1', '4.2']`; `OrientationPlanner::plan(array $orderedIncludedKeys, ?array $landscapeOverride): array` → list of chunks `['orientation' => 'portrait'|'landscape', 'keys' => string[]]`, consecutive sections with the same orientation grouped; the cover is always the first key of the first (portrait) chunk.
- Produces: `PdfExporter::render(MonthlyReport $report): string` **now returns merged PDF bytes** (previously a DomPDF object). `PdfExporter::html(MonthlyReport $report, ?array $keys = null, string $orientation = 'portrait'): string` renders one chunk (all included sections when `$keys` is null — keeps existing tests working). Controller `exportPdf` returns `response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="…"'])`.
- Produces: `layout.blade.php` accepts `$orientation` and emits `@page { size: a4 landscape; margin: 22mm 12mm 18mm 12mm; }` for landscape; the header block is unchanged; **the DomPDF page_text footer is removed** (the merger stamps it).
- `options.landscape_sections`: validated in `update` as `array` of strings ∈ `SectionRegistry::all()` keys minus `cover`; stored in `monthly_reports.options.landscape_sections`; `null`/absent → defaults.

- [ ] **Step 1: Write the failing planner test**

```php
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
```

- [ ] **Step 2: Run to verify it fails** — `php vendor/bin/phpunit tests/Unit/MonthlyReport/OrientationPlannerTest.php` → class not found.

- [ ] **Step 3: Implement the planner, exporter and layout**

`OrientationPlanner::plan`: `$landscape = $override === null ? self::DEFAULT_LANDSCAPE : array_values(array_diff($override, ['cover']))`; iterate keys, `orientation = in_array($key, $landscape) ? 'landscape' : 'portrait'`, push to the current chunk when equal, else start a new chunk.

`PdfExporter::render`:
```php
public function render(MonthlyReport $report): string
{
    $keys = $report->sections->where('include', true)->sortBy('sort_order')->pluck('key')->values()->all();
    $chunks = OrientationPlanner::plan($keys, $report->options['landscape_sections'] ?? null);

    $parts = [];
    foreach ($chunks as $chunk) {
        $html = $this->html($report, $chunk['keys'], $chunk['orientation']);
        $parts[] = ['pdf' => Pdf::loadHTML($html)->setPaper('a4', $chunk['orientation'])->output()];
        if (in_array('2.5', $chunk['keys'], true)) {
            $parts = array_merge($parts, $this->ganttParts($report)); // Task 5 fills this in; returns [] for now
        }
    }

    return $this->merger->merge($parts, "Monthly Progress Report No.{$report->report_no}");
}
```
`ganttParts()` returns `[]` in this task (stub with a comment "Task 5"). Remove the `page_text` canvas call. `html()` gains `$keys`/`$orientation` parameters and passes `orientation` to the layout; each section partial receives `$orientation` too.

`s4-1`/`s4-2`: when `$orientation === 'landscape'`, render the whole period in **one** table (all days, 5.5pt font, `table-layout:fixed`) with the month header row spanning its days; keep the existing 16-day chunking for portrait. `s2-3`: in landscape use 7pt and let all IPC columns fit without wrapping numbers (`white-space:nowrap` on numeric cells).

Controller `update`: add `'options.landscape_sections' => ['sometimes', 'nullable', 'array']`, `'options.landscape_sections.*' => ['string', Rule::in(array_diff(array_keys(SectionRegistry::all()), ['cover']))]`.

- [ ] **Step 4: Extend PdfExportTest**

```php
public function test_render_returns_merged_pdf_with_landscape_pages_and_global_numbering(): void
{
    $report = $this->makeReportWithSeries();
    $bytes = app(PdfExporter::class)->render($report);

    $this->assertStringStartsWith('%PDF', $bytes);
    $this->assertMatchesRegularExpression('/MediaBox \[0 0 841\.\d+ 595\.\d+\]/', $bytes); // at least one landscape page
    $this->assertMatchesRegularExpression('/MediaBox \[0 0 595\.\d+ 841\.\d+\]/', $bytes); // and a portrait one
    $this->assertStringContainsString('Page 1 of ', $bytes);
}
```
Update the existing `exportPdf` API test expectations (response still `application/pdf`, filename unchanged).

- [ ] **Step 5: Run all MPR tests, regenerate the demo PDF, inspect**

`php -d memory_limit=2G vendor/bin/phpunit tests/Feature/MonthlyReport tests/Unit/MonthlyReport` → PASS.
Regenerate `storage/app/mpr-demo-no3.pdf` using `render()` (now bytes: `file_put_contents(..., app(PdfExporter::class)->render($report))`). Check: 2.2–2.4 and 4.1–4.2 pages are landscape, header logos still positioned, footer "Page X of Y" continuous across orientation changes, no blank trailing pages between chunks (if DomPDF emits a trailing blank page after a `page-break`, remove the break on the last section of a chunk).

- [ ] **Step 6: Commit**

```bash
php vendor/bin/pint --test
git add app/Services/MonthlyReport/Export app/Http/Controllers/Api/MonthlyReportController.php resources/views/pdf/monthly-report tests
git commit -m "Merge portrait and landscape chunks with global page numbering"
```

---

### Task 5: Gantt page assets — API, storage, append to PDF

**Files:**
- Create: `app/Services/MonthlyReport/AssetService.php`, `app/Http/Controllers/Api/MonthlyReportAssetController.php`, `resources/views/pdf/monthly-report/asset-image.blade.php`
- Modify: `routes/api.php`, `app/Models/MonthlyReportAsset.php` (fillable/casts check), `app/Services/MonthlyReport/Export/PdfExporter.php` (`ganttParts`), `app/Services/MonthlyReport/MonthlyReportService.php` (delete report → delete asset files; duplicateStatic does **not** copy assets)
- Test: `tests/Feature/MonthlyReport/AssetApiTest.php`

**Interfaces:**
- Routes (all under `auth:sanctum`, prefix `monthly-reports/{report}`):
  - `GET /assets` — `reports.view` — list `[{id, kind, original_name, extension, size, pages, sort_order, created_at}]` ordered by `sort_order, id`
  - `POST /assets` — `reports.manage` — multipart `file` (`required|file|extensions:pdf,png,jpg,jpeg|max:20480`), `kind` (`in:gantt_page,custom`, default `gantt_page`) → 201 with the asset; 422 when finalised; 422 with the FPDI message when a PDF fails `PdfMerger::probe`
  - `PUT /assets/{asset}` — `reports.manage` — `{sort_order: int}` → 200
  - `DELETE /assets/{asset}` — `reports.manage` — deletes the file and row → 200; 422 when finalised
  - `{asset}` must belong to `{report}` else 404 (`MonthlyReportAsset::where('report_id', $report->id)->findOrFail($id)`)
- Storage: disk `local`, path `monthly-reports/{report_id}/assets/{uuid}.{ext}`; `monthly_report_assets` columns used: `report_id, kind, file_path, sort_order` plus **new nullable columns** `original_name string`, `extension string(8)`, `size unsignedInteger`, `pages unsignedSmallInteger` via a new migration `2026_09_20_000001_add_file_meta_to_monthly_report_assets.php` (additive, guarded with `Schema::hasColumn`).
- `PdfExporter::ganttParts($report)`: for each `gantt_page` asset ordered by `sort_order`: PDF → `['file' => Storage::disk('local')->path($file_path)]`; PNG/JPG → render `asset-image.blade.php` (landscape A4, image scaled to fit the printable area, no header) through DomPDF and push `['pdf' => …]`.
- `MonthlyReportService::delete`/`destroy` path: delete the asset files (`Storage::disk('local')->delete(...)`) before the row cascade.

- [ ] **Step 1: Write the failing tests**

```php
<?php
namespace Tests\Feature\MonthlyReport;

use App\Models\MonthlyReport;
use App\Models\MonthlyReportAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetApiTest extends TestCase
{
    use RefreshDatabase;

    // reuse the report/user factory helpers from MonthlyReportApiTest (copy the private helpers; do not extend that class)

    public function test_upload_png_gantt_page_and_list(): void
    {
        Storage::fake('local');
        [$manager, $report] = $this->managerAndReport();

        $res = $this->actingAs($manager)->postJson("/api/monthly-reports/{$report->id}/assets", [
            'file' => UploadedFile::fake()->create('gantt-1.png', 120, 'image/png'),
        ]);

        $res->assertCreated()->assertJsonPath('data.kind', 'gantt_page')->assertJsonPath('data.extension', 'png');
        Storage::disk('local')->assertExists($res->json('data.file_path'));
        $this->actingAs($manager)->getJson("/api/monthly-reports/{$report->id}/assets")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_upload_pdf_probes_readability_and_stores_page_count(): void
    {
        Storage::fake('local');
        [$manager, $report] = $this->managerAndReport();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML('<p>a</p><div style="page-break-before:always">b</div>')->output();

        $res = $this->actingAs($manager)->postJson("/api/monthly-reports/{$report->id}/assets", [
            'file' => UploadedFile::fake()->createWithContent('gantt.pdf', $pdf),
        ]);

        $res->assertCreated()->assertJsonPath('data.pages', 2);
    }

    public function test_unreadable_pdf_is_rejected_with_guidance(): void
    {
        Storage::fake('local');
        [$manager, $report] = $this->managerAndReport();

        $this->actingAs($manager)->postJson("/api/monthly-reports/{$report->id}/assets", [
            'file' => UploadedFile::fake()->createWithContent('bad.pdf', 'not a pdf'),
        ])->assertStatus(422)->assertJsonFragment(['message' => 'This PDF uses a compression the report merger cannot read. Re-save it as PDF 1.4 (Print to PDF) or upload PNG/JPG pages instead.']);
    }

    public function test_asset_from_another_report_is_404_and_finalised_report_rejects_writes(): void
    {
        Storage::fake('local');
        [$manager, $report] = $this->managerAndReport();
        [$_, $other] = $this->managerAndReport();
        $asset = MonthlyReportAsset::create(['report_id' => $other->id, 'kind' => 'gantt_page', 'file_path' => 'x.png', 'sort_order' => 1]);

        $this->actingAs($manager)->deleteJson("/api/monthly-reports/{$report->id}/assets/{$asset->id}")->assertNotFound();

        $report->update(['status' => 'final']);
        $this->actingAs($manager)->postJson("/api/monthly-reports/{$report->id}/assets", [
            'file' => UploadedFile::fake()->create('g.png', 10, 'image/png'),
        ])->assertStatus(422);
    }

    public function test_viewer_cannot_upload_but_can_list(): void
    {
        Storage::fake('local');
        [$manager, $report] = $this->managerAndReport();
        $viewer = $this->userWithPermissions(['reports.view']);

        $this->actingAs($viewer)->getJson("/api/monthly-reports/{$report->id}/assets")->assertOk();
        $this->actingAs($viewer)->postJson("/api/monthly-reports/{$report->id}/assets", [
            'file' => UploadedFile::fake()->create('g.png', 10, 'image/png'),
        ])->assertForbidden();
    }

    public function test_pdf_export_appends_gantt_pages_after_section_2_5(): void
    {
        Storage::fake('local');
        [$manager, $report] = $this->managerAndReport();
        $this->actingAs($manager)->postJson("/api/monthly-reports/{$report->id}/assets", [
            'file' => UploadedFile::fake()->createWithContent('gantt.pdf', \Barryvdh\DomPDF\Facade\Pdf::loadHTML('<p>GANTT-PAGE</p>')->output()),
        ])->assertCreated();

        $before = preg_match_all('/\/Type\s*\/Page[^s]/', app(\App\Services\MonthlyReport\Export\PdfExporter::class)->render($report->fresh(['sections', 'project', 'period'])));
        MonthlyReportAsset::where('report_id', $report->id)->delete();
        $after = preg_match_all('/\/Type\s*\/Page[^s]/', app(\App\Services\MonthlyReport\Export\PdfExporter::class)->render($report->fresh(['sections', 'project', 'period'])));

        $this->assertSame($after + 1, $before);
    }
}
```
Note: `Storage::fake('local')` + FPDI probing needs a real path — `Storage::disk('local')->path()` works with the fake disk.

- [ ] **Step 2: Run to verify they fail** — route not found (404/405).

- [ ] **Step 3: Implement migration, service, controller, routes, exporter append**

Migration (additive):
```php
Schema::table('monthly_report_assets', function (Blueprint $table) {
    if (! Schema::hasColumn('monthly_report_assets', 'original_name')) $table->string('original_name')->nullable()->after('file_path');
    if (! Schema::hasColumn('monthly_report_assets', 'extension')) $table->string('extension', 8)->nullable()->after('original_name');
    if (! Schema::hasColumn('monthly_report_assets', 'size')) $table->unsignedInteger('size')->nullable()->after('extension');
    if (! Schema::hasColumn('monthly_report_assets', 'pages')) $table->unsignedSmallInteger('pages')->nullable()->after('size');
});
```
`AssetService::store(MonthlyReport $report, UploadedFile $file, string $kind)`: guard `isFinal()` → `ValidationException::withMessages(['report' => 'Finalised reports cannot be changed.'])`; `$ext = strtolower($file->getClientOriginalExtension())`; `$path = $file->storeAs("monthly-reports/{$report->id}/assets", Str::uuid().'.'.$ext, 'local')`; for `pdf`: `try { $pages = $merger->probe(Storage::disk('local')->path($path)); } catch (\RuntimeException $e) { Storage::disk('local')->delete($path); throw ValidationException::withMessages(['file' => $e->getMessage()]); }`; images → `pages = 1`; `sort_order = max + 1`. `reorder(asset, int)`, `delete(asset)` (file + row, finalised guard).

Controller returns the standard envelope; `store` → `$this->created(...)`. Routes:
```php
Route::prefix('monthly-reports/{report}/assets')->group(function () {
    Route::get('/', [MonthlyReportAssetController::class, 'index'])->middleware('permission:reports.view');
    Route::post('/', [MonthlyReportAssetController::class, 'store'])->middleware('permission:reports.manage');
    Route::put('/{asset}', [MonthlyReportAssetController::class, 'update'])->middleware('permission:reports.manage');
    Route::delete('/{asset}', [MonthlyReportAssetController::class, 'destroy'])->middleware('permission:reports.manage');
});
```
Register these **before** `Route::prefix('monthly-reports')` `{report}` routes so `/assets` is not swallowed by `GET /{report}`-style patterns (verify with `php artisan route:list --path=monthly-reports`).

`asset-image.blade.php`: `@page { size: a4 landscape; margin: 8mm; }`, `<img src="{{ $uri }}" style="max-width:100%; max-height:190mm; display:block; margin:auto;">` where `$uri` is a data URI built with the extension→MIME map (`png`→`image/png`, `jpg|jpeg`→`image/jpeg`).

- [ ] **Step 4: Run tests** — `php -d memory_limit=2G vendor/bin/phpunit tests/Feature/MonthlyReport` → PASS.

- [ ] **Step 5: Commit**

```bash
php vendor/bin/pint --test
git add database/migrations app/Services/MonthlyReport app/Http/Controllers/Api/MonthlyReportAssetController.php app/Models/MonthlyReportAsset.php routes/api.php resources/views/pdf/monthly-report/asset-image.blade.php tests/Feature/MonthlyReport/AssetApiTest.php
git commit -m "Add monthly report assets API and append Gantt pages to the PDF"
```

---

### Task 6: Chart preview endpoint

**Files:**
- Modify: `app/Http/Controllers/Api/MonthlyReportController.php` (`chart`), `routes/api.php`
- Test: extend `MonthlyReportApiTest`

**Interfaces:**
- `GET /api/monthly-reports/{report}/charts/{key}` — `reports.view` — `key ∈ {2.2, 2.4}` else 404; returns `image/svg+xml` of `SCurveSvg::render` over the **merged** series (data ⊕ overrides) with the same titles/units as the PDF; `Cache-Control: no-store`.

- [ ] **Step 1: Test**
```php
public function test_chart_endpoint_returns_svg_for_s_curve_sections(): void
{
    [$manager, $report] = $this->managerAndReport();
    $this->actingAs($manager)->get("/api/monthly-reports/{$report->id}/charts/2.2")
        ->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    $this->actingAs($manager)->get("/api/monthly-reports/{$report->id}/charts/1.1")->assertNotFound();
}
```
- [ ] **Step 2: Run → fails (404 for 2.2).**
- [ ] **Step 3: Implement** — controller method `chart(MonthlyReport $report, string $key)`; `abort_unless(in_array($key, ['2.2', '2.4']), 404)`; section `->merged['series']`; `response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'no-store'])`. Extract the title/unit map into `SCurveSvg::optionsFor(string $key): array` so the exporter and endpoint share it.
- [ ] **Step 4: Run → PASS. Commit** `"Add S-curve chart preview endpoint"`.

---

### Task 7: Editor — chart preview, Gantt pages panel, landscape options

**Files:**
- Create: `resources/js/pages/reports/editor/ChartSection.jsx`, `GanttAssetsPanel.jsx`, `ReportOptionsDialog.jsx`
- Modify: `resources/js/services/monthlyReportService.js`, `resources/js/pages/reports/editor/sectionConfig.js`, `resources/js/pages/reports/MonthlyReportEditor.jsx`, `resources/js/pages/reports/editor/TextSection.jsx` (2.5 host)

**Interfaces:**
- Service: `listAssets(id)`, `uploadAsset(id, file, kind='gantt_page')` (multipart via `apiClient.post(url, formData)` — do not set `Content-Type` manually), `updateAsset(id, assetId, {sort_order})`, `deleteAsset(id, assetId)`, `getChartUrl(id, key)` → `/api/monthly-reports/${id}/charts/${key}?t=${Date.now()}`.
- `sectionConfig.js`: `'2.2': {type: 'chart', unit: '%'}`, `'2.4': {type: 'chart', unit: 'RM'}` (replacing `text`); `'2.5': {type: 'gantt'}` (replacing the placeholder type); `'4.3'` stays `text` but its read-only render becomes the 24-hour grid (same rule as the Blade: cell filled when the hour overlaps an interval).
- `ChartSection`: `<img src={getChartUrl(...)}>` (re-fetched after Save/Regenerate via a `version` prop from the shell) above the existing series table (months/scheduled/actual editable as today for `text`→ now a small `TableSection`-style grid with columns month(readOnly), scheduled(number), actual(number)); saves `overrides: {series: {months, scheduled, actual}}` — confirm `SectionMerger::validateOverrides` accepts `series` as a map override (it does: map replaced/merged by key); if `sameKind` rejects, extend the merger to allow it and add a unit test.
- `GanttAssetsPanel` (2.5): dropzone/file input (`accept=".pdf,.png,.jpg,.jpeg"`), list rows `original_name · pages p · size`, up/down reorder (PUT sort_order swap), delete with `useConfirm`, upload errors shown via toast **including the 422 FPDI guidance message**; disabled when final or no `reports.manage`. Also shows the note "Activity table import arrives in a later phase."
- `ReportOptionsDialog`: opened from a header "Layout" button; checkbox list of all sections except cover with a "Landscape" toggle each, pre-checked from `report.options?.landscape_sections ?? DEFAULT_LANDSCAPE` (`['2.2','2.3','2.4','2.5','4.1','4.2']` duplicated in `sectionConfig.js` as `DEFAULT_LANDSCAPE`); Save → `update(id, {options: {...report.options, landscape_sections}})`; "Reset to defaults" sends `landscape_sections: null`.
- Left-nav badge `landscape` (small "L" pill) for sections currently landscape.

- [ ] **Step 1: Implement the service methods, config, and the three components; wire into the shell.**
- [ ] **Step 2: `npm run build`; smoke-check with `npx esbuild` bundling of `MonthlyReportEditor.jsx` (externalising `@/*` and node_modules) for syntax; commit source + `public/build/**`.**

```bash
git add resources/js public/build
git commit -m "Add chart preview, Gantt pages panel and landscape options to the report editor"
```

---

### Task 8: Panduan, local demo assets, full verification

**Files:**
- Modify: `resources/js/pages/panduan/content.js` (group "Laporan Bulanan": add bullets for carta automatik, halaman landscape & butang Layout, muat naik halaman Gantt (PDF 1.4 / PNG), nota fasa seterusnya: Word + import program)
- Local only (git-excluded): extend `database/seeders/ReportDataDemoSeeder.php` to attach one generated Gantt PDF (2 pages, rendered with DomPDF from a simple HTML bar list) and one PNG (create via GD if available, else skip) as `gantt_page` assets for report No.3, idempotently (delete existing assets + files first).

- [ ] **Step 1: Panduan + seeder.**
- [ ] **Step 2: `php vendor/bin/pint --test && php -d memory_limit=2G vendor/bin/phpunit && npm run build`; run the seeder twice; regenerate `storage/app/mpr-demo-no3.pdf` via `PdfExporter::render` and inspect: charts on 2.2/2.4 (landscape), IPC 2.3 landscape, 2.5 followed by the Gantt pages, 4.1/4.2 single wide tables, 4.3 grid, continuous "Page X of Y".**
- [ ] **Step 3: Commit** `"Document charts, layout and Gantt pages in the user guide"` (Panduan + `public/build` only).

---

## Self-review

**Spec coverage (Phase C = §8 row C):** S-curves → T2/T3; weather chart → T3; landscape chunks + FPDI merge + page numbering → T1/T4; Gantt page append → T5; editor chart sections + upload UI (§7) → T6/T7; assets routes (§6) → T5; guide → T8. Client PNG snapshots deliberately deferred to Phase D (ruling in Global Constraints).

**Placeholder scan:** every task has concrete code or exact shapes; `ganttParts()` is an explicit stub in T4 filled in T5 (same plan).

**Type consistency:** `PdfMerger::merge(array $parts, string $footerLeft): string` and `probe(): int` (T1) used in T4/T5; `SCurveSvg::render/dataUri/optionsFor` (T2/T6) used in T3/T6; `OrientationPlanner::plan(array, ?array)` and `DEFAULT_LANDSCAPE` (T4) mirrored in `sectionConfig.js` (T7); `PdfExporter::render` returns bytes from T4 onward and the controller is updated in the same task; asset JSON fields (`id, kind, original_name, extension, size, pages, sort_order, file_path`) consistent between T5 API and T7 panel.
