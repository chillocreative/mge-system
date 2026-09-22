# SPEC-006 — environment-report-pdf-layout

## Objective

Make the Environment Report **PDF export** work by supplying the missing Blade view
`resources/views/pdf/environment-report/layout.blade.php`, so
`tests/Feature/EnvironmentReportExportTest.php` goes from 3 failing (HTTP 500) to passing,
without changing what the printed report says.

## Context already verified on disk (do not re-litigate; if any of it is false, return BLOCKED)

The Environment module (water quality + project settings + monthly environment report +
exporters + frontend) was built today and is **uncommitted**. Everything works except the
PDF export.

| Fact | Evidence (measured 2026-09-22) |
|---|---|
| The exporter renders a view that does not exist | `app/Services/Environment/Export/EnvironmentReportPdfExporter.php:15` → `view('pdf.environment-report.layout', $data)->render()` |
| That view is the **only** reference to any `pdf.environment-report.*` name from PHP | `grep -rn "pdf.environment-report" app/ tests/` matches only line 15 above |
| The failure is the 500 in 3 tests | `php artisan test tests/Feature/EnvironmentReportExportTest.php` → `⨯ export pdf download and inline`, `⨯ export pdf with bmp photo asset`, `⨯ export filename sanitised when project code has slash`; `✓ export docx`. Log: `local.ERROR: View [pdf.environment-report.layout] not found.` |
| Existing files in `resources/views/pdf/environment-report/` | `cover.blade.php` (fragment: cover block only), `toc.blade.php` (fragment), `sections/*.blade.php` (9 fragments), `partials/styles.blade.php` (CSS only, no `<style>` tag), `cover-page.blade.php` (full HTML doc, includes **only** `cover`), `body.blade.php` (full HTML doc, header + toc + all 9 sections) |
| `cover-page.blade.php` and `body.blade.php` are dead code | neither is referenced anywhere (see grep row above); they are a half-finished split of the document the exporter asks for |
| Repo precedent for this exact document shape | `resources/views/pdf/monthly-report/layout.blade.php` — one full HTML doc: `<head>` with `@page` + inline CSS, `.header` as `position: fixed`, cover include, `.page-break`, TOC, then sections. The fixed header therefore also prints on the cover page; that is the accepted house behaviour, do not "fix" it here. |
| View-data keys available to the layout | `EnvironmentReportViewData::viewData()` returns `report, project, contract, contract_no, period_label, report_title, report_no, logo_mge, logo_so, so_name, parties{owner,so,consultant,contractor}, signatories, sections, section_numbers, section_titles, policy_image, location_image, bmp_photos, consultant_cert` |
| Migrations are applied locally | `php artisan migrate:status` → `…000008…000011` all `Ran` |

## Files

- **CREATE** `resources/views/pdf/environment-report/layout.blade.php`
- **EDIT** `resources/views/pdf/environment-report/body.blade.php` — become a body *fragment*
- **DELETE** `resources/views/pdf/environment-report/cover-page.blade.php`
- **EDIT** `resources/views/pdf/environment-report/toc.blade.php` + `app/Services/Environment/Export/EnvironmentReportDocxExporter.php` + `app/Services/Environment/Export/EnvironmentReportViewData.php` — one source for TOC sub-items (item 3 below)
- **EDIT** `tests/Feature/EnvironmentReportExportTest.php` — add the `%PDF-` content assertion (item 4 below)

No other file. **No frontend change → do NOT run `npm run build`.**

## Contract

### 1. `layout.blade.php` — the single document the exporter renders

Structure (mirror `pdf.monthly-report.layout`):

```
<!DOCTYPE html><html><head><meta charset="utf-8">
<title>{{ $report_title }}</title>
<style>
    @page { margin: 30mm 14mm 18mm 14mm; }        <- byte-identical to the rule in body.blade.php today
    @include('pdf.environment-report.partials.styles')
</style></head>
<body>
    @include('pdf.environment-report.cover')      <- cover first
    <div class="page-break"></div>
    @include('pdf.environment-report.body')       <- body is now the fragment from item 2
</body></html>
```

### 2. `body.blade.php` becomes a fragment

Delete only its document shell: `<!DOCTYPE html>`, `<html>`, `<head>…</head>` (including the
`@page` rule and the `partials.styles` include — the layout now owns those) and the outer
`<body>`/`</body>` tags. Keep everything that was inside `<body>` **byte-identical**: the
`.header` table, the `<hr>`, `@include('pdf.environment-report.toc')`, and the nine
`<div class="page-break"></div>` + section includes in the current order
(`contract, ems, introduction+flow_chart, policy, location, parameters, results, bmp`).
Do not renumber, reorder, re-space or re-word any of it.

Result: the cover + header + TOC + sections sequence exists in exactly one place.

### 3. TOC sub-items from one source (pure refactor — printed output must not change)

`EnvironmentReportViewData::SECTION_SUBITEMS` (line ~48) is **never read** (verified by grep),
and its values differ from the two live hardcoded copies in `toc.blade.php` and
`EnvironmentReportDocxExporter::writeToc()`, which agree with each other:

```php
'contract'   => [['num' => '1.1', 'title' => 'Contract Information']],
'ems'        => [['num' => '2.1', 'title' => 'Certified Environment Consultant']],
'parameters' => [['num' => '7.1', 'title' => 'Test Parameters'], ['num' => '7.1', 'title' => 'Monitoring Period']],
```

- Set `SECTION_SUBITEMS` to **exactly** the three rows above (the values that print today).
- Add `'section_subitems' => self::SECTION_SUBITEMS,` to the array returned by `viewData()`.
- In `toc.blade.php`, delete the local `$subitems = […]` block and iterate
  `$section_subitems` instead: `@foreach(($section_subitems[$key] ?? []) as $item)`.
- In `EnvironmentReportDocxExporter::writeToc()`, delete the local `$subitems = […]` array and
  read `$data['section_subitems']` (pass `$data` in — the method already receives no `$data`,
  so change its signature to `writeToc(DocxDocument $doc, array $data)` and update the one
  call site `$this->writeToc($doc);` → `$this->writeToc($doc, $data);`).
- The DOCX test (`test_export_docx`) passes today; it must keep passing.

### 4. Prove the PDF has content, not just a 200

In `test_export_pdf_download_and_inline`, after the existing `$download->assertOk();` add:

```php
$bytes = $download->streamedContent();
$this->assertStringStartsWith('%PDF-', $bytes);
$this->assertGreaterThan(5000, strlen($bytes));
```

This is the assertion that distinguishes "a PDF rendered" from "an empty document shipped".

## Constraints

- MUST NOT: change any text, number, table, class or style inside `cover.blade.php`,
  `toc.blade.php` (beyond item 3), `partials/styles.blade.php`, or the nine section views.
  The reference JPN/DOE layout is the deliverable; this work order only supplies the missing
  wrapper.
- MUST NOT: add a `@page margin-box` header, `header_text`, `page_text` footer, or a
  PDF merge/two-document approach. `stampPageNumbers()` in the exporter already handles page
  numbering — leave the exporter alone.
- MUST NOT: touch `EnvironmentReportController`, routes, migrations, models, services other
  than the two named in item 3, or anything under `resources/js/`.
- MUST NOT: weaken or move the CSP / `X-Content-Type-Options` headers in
  `EnvironmentReportController::downloadAsset()` (lines ~183-205) — they are already correct.
- MUST NOT: `git add`, `commit`, `push`, or `migrate --force`.
- MUST NOT: run `npm run build` (nothing in `resources/js/` changes).
- PREFER: cut-and-paste for item 2 — retyping the section sequence is how a section silently
  goes missing.
- ASK: if `body.blade.php` or `toc.blade.php` differs from the description in the Context
  table, STOP and return BLOCKED with the file contents quoted.

## Verification

- [ ] `grep -rn "pdf.environment-report" app/ | head` → quote it. Expect exactly the one
      reference (the exporter's line 15) and no reference to `cover-page`.
- [ ] `ls resources/views/pdf/environment-report/` → quote. Expect
      `body.blade.php cover.blade.php layout.blade.php partials sections toc.blade.php` and
      **no** `cover-page.blade.php`.
- [ ] `php artisan test tests/Feature/EnvironmentReportExportTest.php` → quote the full list
      of test lines. Expect 4 passed, 0 failed. This is the gate: it failed before your change.
- [ ] `php artisan test --filter='Environment|WaterQuality'` → quote the summary line. Expect
      19 passed (it was 16 passed / 3 failed before).
- [ ] `php artisan test` → quote the final `Tests:` summary. The pre-existing baseline is
      what it is: paste it, state whether any failure is one you introduced, and return
      BLOCKED if you cannot make that claim.
- [ ] `php vendor/bin/pint --dirty` → quote output.
- [ ] `git status --short -- resources/views/pdf/environment-report app/Services/Environment tests/Feature/EnvironmentReportExportTest.php` → quote verbatim.
- [ ] Paste the first 15 lines of `layout.blade.php` and the `git diff` of `body.blade.php`
      so the reviewer can see the shell moved rather than the content rewritten.

## Loop control

- Retry budget: 3 REJECT rounds. Turn budget: 60.
- On block: write the question into `.qwen/runs/RUN-006.md`, stop, return `BLOCKED: <question>`.

## Security notes

- Uploaded report assets are served inline with
  `Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'`
  and `X-Content-Type-Options: nosniff`. Untouched by this work order.
- The report HTML is rendered from **stored** section JSON plus `data:` image URIs. No new
  user-controlled string reaches Blade unescaped — do not add `{!! !!}` anywhere; if a section
  genuinely needs raw HTML, return BLOCKED instead.
- `DejaVu Sans` and `partials.styles` inline CSS are the only assets the PDF pulls; the
  document must stay self-contained (no external URL in the layout).

## Report

Write `.qwen/runs/RUN-006.md` with the verbatim output of every checkbox. No PASS claim for a
command you did not run.
