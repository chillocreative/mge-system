# Monthly Progress Report — Phase D: Word (DOCX) Export Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Export any monthly report as an editable Word document (`.docx`) that mirrors the PDF: cover, TOC, every included section (tables, charts, matrices, weather grid, images, Gantt pages), portrait/landscape sections, headers/footers with page numbers, section notes.

**Architecture:** A `DocxExporter` built on `phpoffice/phpword` consumes the **same merged view data** the PDF uses (`PdfExporter::viewData()` is extracted into a shared `ReportViewData` builder). One `DocxWriter` per section family (generic row tables driven by a per-key column spec, plus special writers for cover, org tree, progress, charts, groups, matrices, weather grid, images, Gantt). Charts need PNG in Word (PHPWord has no SVG): the editor snapshots the already-rendered SVG `<img>` onto a `<canvas>` and uploads PNGs as `chart_physical_scurve` / `chart_financial_scurve` assets right before opening the DOCX download; the exporter embeds the latest snapshot, or falls back to the series table with a note. Gantt PNG/JPG assets are embedded as landscape image pages; PDF Gantt assets cannot be embedded in Word and are listed as attachments.

**Tech Stack:** Laravel 12, phpoffice/phpword ^1.3 (new, committed to `vendor/`; needs `ext-zip`, `ext-xml`, `ext-dom` — present locally; **add to deploy notes**), React 19, PHPUnit, Pint. Verification tools available locally: `unzip -p file.docx word/document.xml`, macOS `textutil -convert html`, `qlmanage -t` (first-page thumbnail).

**Spec:** `docs/superpowers/specs/2026-09-17-monthly-progress-report-design.md` — §4 (Export/DocxExporter, chart snapshots), §5.2, §6 (`export/docx`), §7 (header bar Export DOCX), §8 Phase D.

## Global Constraints

- Production PHP lacks `fileinfo`; may lack GD. PHPWord's `addImage` uses `getimagesize()` (core, not GD) for PNG/JPG — acceptable. Never call `finfo`, `mimes:`, `Storage::response()`, `guessExtension()`.
- `vendor/` and `public/build/**` are committed; production runs neither composer nor vite → commit the phpword package tree + composer autoload files, and build assets with every `resources/js` change.
- Permission: `reports.view` for `GET /export/docx` (same as PDF); chart snapshot upload uses the existing assets API (`reports.manage`). Assets scoped by `report_id`.
- Finalised reports: snapshot upload is a write → the editor must NOT attempt snapshots on finalised reports; the exporter uses whatever chart assets exist (or the table fallback). Snapshots are replaced in place (one asset per chart kind per report).
- The DOCX exporter consumes `data ⊕ overrides ⊕ notes` exactly like the PDF (shared `ReportViewData`); no builder is re-run during export.
- Word specifics (spec §5.2): real sections with portrait/landscape orientation; header (client logo left / project title + contract no centre / MGE logo right) and footer ("Monthly Progress Report No.N" left, `PAGE` of `NUMPAGES` right); a `TOC` field the user refreshes with F9 (tell them in the document and the guide); tables: green header shading `D9EAD3`, 1pt borders, repeating header rows; plain content only (no content controls/macros).
- Chart snapshot PNGs ≤ 1 MB each (canvas at 1000×480 CSS px, devicePixelRatio 2 max); validated with `extensions:png` and `max:2048`.
- Local DB: never `migrate:fresh/refresh/rollback/db:wipe`. New migrations additive only.
- Lint/test: `php vendor/bin/pint --test`, `php -d memory_limit=2G vendor/bin/phpunit`, `npm run build`.
- Commit locally on `feature/monthly-report-phase-d` (from `main` @ 9d90673); **no push** until the user approves after local testing.

---

## File structure

| Path | Responsibility |
|---|---|
| `app/Services/MonthlyReport/Export/ReportViewData.php` | Extracted from `PdfExporter::viewData()`: builds `sections/notes/logos/mgeLogo/titles/tocKeys` + image data URIs + chart SVG URIs; used by both exporters |
| `app/Services/MonthlyReport/Export/PdfExporter.php` | Now delegates to `ReportViewData` (behaviour unchanged) |
| `app/Services/MonthlyReport/Export/Docx/DocxDocument.php` | PHPWord wrapper: styles, `newSection($orientation)`, header/footer, TOC, heading, paragraph, note, `table($headers, $rows, $opts)`, `image($binary, $opts)`, `pageBreak()`, `save(): string` |
| `app/Services/MonthlyReport/Export/Docx/DocxExporter.php` | Orchestrates: view data → sections in order → writers → bytes |
| `app/Services/MonthlyReport/Export/Docx/SectionSpecs.php` | Per-key column specs for generic row tables (mirrors the Blade views) |
| `app/Services/MonthlyReport/Export/Docx/Writers/*.php` | `CoverWriter`, `KeyValueWriter` (1.1), `RowsWriter` (generic), `OrgChartWriter` (1.4), `ProgressWriter` (2.1), `ChartWriter` (2.2/2.4), `GanttWriter` (2.5), `GroupsNumbersWriter` (3.1), `GroupsRowsWriter` (3.2), `MatrixWriter` (4.1/4.2), `WeatherWriter` (4.3), `ImagesWriter` (1.3/5.0) |
| `app/Http/Controllers/Api/MonthlyReportController.php` | `exportDocx` |
| `app/Http/Controllers/Api/MonthlyReportAssetController.php`, `AssetService.php` | accept `kind` chart_physical_scurve / chart_financial_scurve (PNG only, replace-in-place) |
| `resources/js/services/monthlyReportService.js` | `getDocxUrl(id)`, `uploadAsset(id, file, kind)` reuse |
| `resources/js/pages/reports/editor/chartSnapshot.js` | `snapshotChartPng(svgUrl) → Blob` (Image → canvas → toBlob) |
| `resources/js/pages/reports/MonthlyReportEditor.jsx`, `MonthlyReports.jsx` | "Export Word" button (snapshot → upload → open URL); list-page Word link |
| `tests/Unit/MonthlyReport/DocxDocumentTest.php`, `tests/Feature/MonthlyReport/DocxExportTest.php` | Tests (inspect `word/document.xml` via `ZipArchive`) |

---

### Task 1: PHPWord dependency + `DocxDocument` wrapper + shared `ReportViewData`

**Files:**
- Modify: `composer.json`, `composer.lock`, `vendor/**`
- Create: `app/Services/MonthlyReport/Export/Docx/DocxDocument.php`, `app/Services/MonthlyReport/Export/ReportViewData.php`
- Modify: `app/Services/MonthlyReport/Export/PdfExporter.php` (delegate `viewData()` → `ReportViewData::build($report)`; keep the public surface and existing tests green)
- Test: `tests/Unit/MonthlyReport/DocxDocumentTest.php`

**Interfaces:**
- `ReportViewData::build(MonthlyReport $report): array` returns exactly what `PdfExporter::viewData()` returned (`report, ctx, sections, notes, logos, mgeLogo, titles`) plus `tocKeys` (registry order ∩ included). `PdfExporter` keeps a private `viewData()` that calls it (so `html()`/`render()` are unchanged).
- `DocxDocument`:
  - `__construct(array $meta)` — `meta = ['title' => 'Monthly Progress Report No.3', 'project_title' => …, 'contract_no' => …, 'client_logo' => ?binary, 'mge_logo' => ?binary]`
  - `newSection(string $orientation = 'portrait'): void` — A4, margins 20 mm (top 26 mm to leave room for the header), orientation; every section gets the standard header/footer.
  - `heading(string $text, int $level = 1)`, `paragraph(string $text, array $style = [])`, `note(?string $text)` (italic grey, skipped when empty), `toc()` (PHPWord `addTOC` + a one-line instruction "Right-click → Update Field (or press F9) to refresh the table of contents."), `pageBreak()`
  - `table(array $headers, array $rows, array $opts = []): void` — `headers` list of strings (or `null` for no header row); `rows` list of lists (cells may be string|int|float|null → `'-'` for null/''); opts: `widths` (twips or percentages), `fontSize` (default 8), `align` per column (`['r','l',…]`), `repeatHeader` (default true), `shading` per cell callback `fn($r,$c) => '60A5FA'|null`, `bold` rows (list of indices), `colspan` support via cells given as `['text' => …, 'colspan' => n]`.
  - `image(string $binary, array $opts = [])` — `width` mm (default full text width), `height` auto, `align` center; writes to a temp file for PHPWord (`addImage($path)`) and cleans up on `save()`.
  - `keyValue(array $pairs)` — 2-column table without header (label bold).
  - `save(): string` — DOCX bytes (`IOFactory::createWriter($phpWord, 'Word2007')->save($tmp)` → read → unlink).
- Styles: `Heading1` (11pt bold), `Heading2` (10pt bold), body 9pt, default font `Arial`; table header shading `D9EAD3`, borders 1pt `000000` (`borderSize` 8 twips), cell margins 40 twips.

- [ ] **Step 1: Install**
```bash
composer require phpoffice/phpword:^1.3
git status --short vendor | grep -v "vendor/phpoffice\|vendor/composer" | head   # must be empty (only phpoffice + autoload; phpword may pull in symfony/polyfill or laminas packages — those are allowed, list them in the report)
```
- [ ] **Step 2: Failing test**
```php
<?php
namespace Tests\Unit\MonthlyReport;

use App\Services\MonthlyReport\Export\Docx\DocxDocument;
use PHPUnit\Framework\TestCase;

class DocxDocumentTest extends TestCase
{
    public function test_builds_document_with_two_orientations_table_and_toc(): void
    {
        $doc = new DocxDocument(['title' => 'Monthly Progress Report No.9', 'project_title' => 'RTB SG.MUAR', 'contract_no' => 'JPS/1']);
        $doc->newSection('portrait');
        $doc->toc();
        $doc->heading('1.1 PROJECT INFORMATION');
        $doc->table(['Item', 'Value'], [['Contract Sum', 'RM 288,000,000.00'], ['DLP', null]]);
        $doc->note('Handwritten note');
        $doc->newSection('landscape');
        $doc->heading('4.1 MONTHLY TRADE WORKER');
        $doc->table(['No.', 'Desc', '1', '2'], [[1, 'GW', 3, null]], ['shading' => fn ($r, $c) => $c === 2 ? '60A5FA' : null]);

        $bytes = $doc->save();
        $xml = $this->documentXml($bytes);

        $this->assertStringStartsWith("PK", $bytes);
        $this->assertStringContainsString('w:orient="landscape"', $xml);
        $this->assertStringContainsString('TOC \o', $xml);
        $this->assertStringContainsString('1.1 PROJECT INFORMATION', $xml);
        $this->assertStringContainsString('w:fill="D9EAD3"', $xml);
        $this->assertStringContainsString('w:fill="60A5FA"', $xml);
        $this->assertStringContainsString('Handwritten note', $xml);
        $this->assertStringContainsString('NUMPAGES', $this->partXml($bytes, 'word/footer1.xml'));
        $this->assertStringContainsString('RTB SG.MUAR', $this->partXml($bytes, 'word/header1.xml'));
    }

    private function documentXml(string $bytes): string { return $this->partXml($bytes, 'word/document.xml'); }

    private function partXml(string $bytes, string $part): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($tmp, $bytes);
        $zip = new \ZipArchive; $zip->open($tmp);
        $xml = (string) $zip->getFromName($part); $zip->close(); unlink($tmp);
        return $xml;
    }
}
```
Header/footer part names: PHPWord numbers them per section (`header1.xml`, `footer1.xml`, …) — adjust the assertion to whichever part exists (loop over parts matching `word/header*.xml`).
- [ ] **Step 3: Run → fails.** `php vendor/bin/phpunit tests/Unit/MonthlyReport/DocxDocumentTest.php`
- [ ] **Step 4: Implement** `DocxDocument` and `ReportViewData`; make `PdfExporter` delegate; run `tests/Feature/MonthlyReport` (PDF tests must stay green).
- [ ] **Step 5: Run → PASS. Commit** `"Add PHPWord and the DOCX document builder"` (include `composer.*`, `vendor/phpoffice/**`, other new vendor dirs composer added, `vendor/composer/**`).

---

### Task 2: Chart snapshot assets (API + editor)

**Files:**
- Modify: `app/Services/MonthlyReport/AssetService.php`, `app/Http/Controllers/Api/MonthlyReportAssetController.php`
- Create: `resources/js/pages/reports/editor/chartSnapshot.js`
- Modify: `resources/js/services/monthlyReportService.js`, `resources/js/pages/reports/MonthlyReportEditor.jsx`
- Test: extend `tests/Feature/MonthlyReport/AssetApiTest.php`

**Interfaces:**
- `POST /monthly-reports/{report}/assets` accepts `kind ∈ {gantt_page, custom, chart_physical_scurve, chart_financial_scurve}`; chart kinds require `extensions:png`, `max:2048`, and **replace** any existing asset of the same kind for that report (delete old file + row inside the transaction); `sort_order` 0; they never auto-include 2.5; `GET /assets` lists them too (the Gantt panel filters `kind === 'gantt_page'` client-side — check `GanttAssetsPanel.jsx` and add the filter).
- Frontend `chartSnapshot.js`: `export async function snapshotChartPng(url, {width = 1000, height = 480, scale = 2} = {}): Promise<Blob>` — `fetch(url, {credentials: 'include'})` → SVG text → `new Image()` with `src = 'data:image/svg+xml;base64,…'` → draw on a canvas `width*scale × height*scale` with white background → `canvas.toBlob('image/png')`. Reject with a readable error on load failure.
- `monthlyReportService.getDocxUrl(id)` → `/api/monthly-reports/${id}/export/docx`.
- Editor: new **Export Word** button next to Export PDF. On click: if `!isFinal && canManage` → for each of `2.2`/`2.4` that is included: snapshot via `getChartUrl(id, key, chartVersion)` and `uploadAsset(id, new File([blob], 'chart-2.2.png'), 'chart_physical_scurve' | 'chart_financial_scurve')`; toast progress ("Preparing charts…"); on any snapshot failure show a toast "Charts could not be captured — the Word file will contain the data tables instead" and continue. Then `window.open(getDocxUrl(id), '_blank', 'noopener')` (the same anchor pattern as the PDF is fine: use a hidden `<a>` click so pop-up blockers don't interfere). Finalised reports or view-only users: just open the URL.
- Tests: chart kind PNG upload replaces the previous one (count stays 1, old file removed); chart kind with a `.jpg` → 422; chart kinds do not flip 2.5 include.

- [ ] Implement backend + tests → PASS; implement frontend; `npm run build`; commit `"Add chart snapshot assets and the Export Word button"` (source + build).

---

### Task 3: `DocxExporter` core + generic writers

**Files:**
- Create: `Docx/DocxExporter.php`, `Docx/SectionSpecs.php`, `Docx/Writers/SectionWriter.php` (interface: `write(DocxDocument $doc, string $key, array $data, ?string $note, array $ctx): void`), `CoverWriter.php`, `KeyValueWriter.php`, `RowsWriter.php`, `TextWriter.php` (fallback: renders `rows` if present else "No data.")
- Modify: `app/Http/Controllers/Api/MonthlyReportController.php` (`exportDocx`), `routes/api.php` (`GET /{report}/export/docx`, `reports.view`, before `/{report}`)
- Test: `tests/Feature/MonthlyReport/DocxExportTest.php`

**Interfaces:**
- `DocxExporter::render(MonthlyReport $report): string` (bytes). Flow: `ReportViewData::build` → `OrientationPlanner::plan($includedKeys, $options['landscape_sections'])` → for each chunk `newSection($orientation)`; first chunk: cover (CoverWriter) + page break + TOC + page break; each section: `heading("{$key} {$title}")` → writer → `note()` → page break between sections (not after the last of a chunk).
- `SectionSpecs::for(string $key): ?array` → `['columns' => [['key' => 'party', 'label' => 'Party'], …], 'rowsKey' => 'rows']` mirroring the Blade views for: 1.2 (party, company, address, contacts → contacts flattened as "Name — Designation — Tel — Email" lines), 1.5, 2.3 (IPC columns incl. money formatting `number_format(,2)`), 2.6, 3.4, 3.6, 3.7, 2.5 rows. Read each `s*.blade.php` to copy the exact column order, labels and formatting.
- `KeyValueWriter` for 1.1 (`rows` of label/value → `keyValue`); `CoverWriter` mirrors `cover.blade.php` (client/logo, titles, contract no, report title, period label, evaluation date, signatories 3-column table); `RowsWriter` generic; writer registry in `DocxExporter::WRITERS = ['cover' => CoverWriter::class, '1.1' => KeyValueWriter::class, '1.2' => RowsWriter::class, …]` with `TextWriter` default. Keys handled in Task 4 temporarily map to `TextWriter`.
- Controller `exportDocx`: `set_time_limit(120)`, `ini_set('memory_limit','512M')`, filename `MPR-{code}-No{n}.docx`, `Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document`.
- Test: `test_docx_export_contains_cover_toc_and_row_sections` — uses the fixtures of `PdfExportTest` (copy helpers); asserts document.xml contains the project title, "Table of Contents"/`TOC \o`, "1.1 PROJECT INFORMATION", a 1.2 party name, footer NUMPAGES; API test `GET /export/docx` → 200 + content-type; `reports.view`-only user 200; unauthenticated 401.

- [ ] Failing tests → implement → PASS → commit `"Add DOCX exporter with cover, TOC and table sections"`.

---

### Task 4: Special writers (org chart, progress, charts, Gantt, groups, matrices, weather, images)

**Files:**
- Create: `Writers/OrgChartWriter.php` (1.4: indented tree list, level × 0.6 cm indent, "TBA" rows as-is — mirror `s1-4.blade.php`/`s1-4-node.blade.php`), `ProgressWriter.php` (2.1: the two fixed tables physical/financial with prev/cur labels), `ChartWriter.php` (2.2/2.4: embed the latest `chart_*` asset PNG at 170 mm wide (landscape) / 160 mm (portrait) if present, else a paragraph "Chart not captured — open the report in the editor and export again to embed the chart."; then the month series tables chunked 12/13 like the PDF), `GanttWriter.php` (2.5: rows table if any; then each `gantt_page` asset: PNG/JPG → new landscape section + full-width image; PDF → bullet "Attached: {file_name} ({pages} pages) — see the PDF export for the embedded pages"), `GroupsNumbersWriter.php` (3.1), `GroupsRowsWriter.php` (3.2: heading per group + RowsWriter table), `MatrixWriter.php` (4.1/4.2: No./Description + one column per day, month header row with colspan, 6pt font, totals row computed from rows like the Blade, groups as full-width bold rows; in portrait chunk by 16 days, landscape all days), `WeatherWriter.php` (4.3: Date + 24 hour columns + Hrs; shaded `60A5FA` when `s < (h+1)*60 && e > h*60`; summary key-values), `ImagesWriter.php` (1.3: 2-column grid of images 80 mm wide with captions; 5.0: site_access, key_plan grids + pairs table Previous | Current with captions; images from the data URIs already in view data → decode base64).
- Modify: `DocxExporter::WRITERS`.
- Test: extend `DocxExportTest` with `test_docx_export_renders_special_sections` (fixtures with periods/baseline/site logs/images/chart asset): assert presence of "2.1", "Previous"/"Current", a month label in 2.2, `w:fill="60A5FA"` for 4.3, a trade category name for 4.1, an `<w:drawing>` (image) when a chart asset exists and the "Chart not captured" text when it doesn't.

- [ ] Failing test → implement → PASS → regenerate a local demo DOCX: `php artisan tinker --execute="file_put_contents('storage/app/mpr-demo-no3.docx', app(\App\Services\MonthlyReport\Export\Docx\DocxExporter::class)->render(\App\Models\MonthlyReport::with(['sections','project','period'])->where('project_id',3)->firstOrFail()));"` then `textutil -convert html storage/app/mpr-demo-no3.docx -output /tmp/mpr.html` and `qlmanage -t -s 1200 -o /tmp storage/app/mpr-demo-no3.docx`; report anomalies → commit `"Render charts, matrices, weather grid and images in the DOCX export"`.

---

### Task 5: Editor/list polish + Panduan + demo + verification

**Files:**
- Modify: `resources/js/pages/reports/MonthlyReports.jsx` (Word link beside PDF in the actions column — plain link, no snapshot), `resources/js/pages/panduan/content.js` (steps: butang **Export Word** — carta dirakam automatik sebelum muat turun (laporan final guna rakaman terakhir), buka dalam Word → tekan F9 / "Update field" untuk kemas kini Isi Kandungan; halaman Gantt PDF tidak boleh dimasukkan ke Word — gunakan PNG jika perlu dalam Word; jadual boleh diedit bebas).
- Local only: extend `database/seeders/ReportDataDemoSeeder.php` to attach two chart PNG snapshots to report No.3 (generate with GD if available: draw the polyline from the series onto a 1000×480 white canvas; else skip with a note).
- Verification: `pint --test`, full `phpunit`, `npm run build`, seeder twice (1 report, asset count), regenerate demo PDF and DOCX; `textutil`/`qlmanage` checks.
- Commit `"Document Word export in the user guide"` (content.js + build only).

---

## Self-review

**Spec coverage (Phase D = §8 row D):** PHPWord exporter (T1/T3/T4), TOC + headers/footers (T1), images from PNG snapshots (T2 upload, T4 embed), route + button (T3/T2), guide (T5). Both exporters consume the same merged view (T1 `ReportViewData`).

**Placeholder scan:** every task names concrete files, signatures, assertions; writers list the exact Blade files they mirror.

**Type consistency:** `DocxDocument` API (`newSection/heading/paragraph/note/toc/table/image/keyValue/pageBreak/save`) used by all writers; `SectionWriter::write(DocxDocument, string, array, ?string, array)`; asset kinds `chart_physical_scurve`/`chart_financial_scurve` identical in T2 API, T2 frontend and T4 `ChartWriter`; `getDocxUrl` in T2 and T5.
