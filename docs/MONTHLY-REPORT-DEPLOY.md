# Monthly Progress Report — Deployment Notes

Deployment prerequisites, storage layout, limits and known limitations for the
Monthly Progress Report feature (Phases A–F).

## PHP extensions

| Extension | Required for | Notes |
|---|---|---|
| `zip` | DOCX export (PhpWord) and XLSX import/export (PhpSpreadsheet) | Both write/read `.xlsx`/`.docx`, which are zip containers. |
| `xml` | DOCX export, MS Project XML (MSPDI) programme import | |
| `dom` | DOCX export (PhpWord), XLSX read/write (PhpSpreadsheet) | |
| `fileinfo` | **Any** file upload (Report Images, programme XLSX/XML, Gantt PDF/PNG, monthly report assets) | Missing `fileinfo` crashes Laravel's local Flysystem driver with `Error: Class "finfo" not found` the moment an uploaded file is stored — this is the single most common "Server Error" report. See CLAUDE.md note #5. Enable in cPanel → Select PHP Version → Extensions (or MultiPHP INI Editor) for the app's PHP version. |
| `gd` (optional) | Downscaling uploaded Report Images (progress/location photos); Gantt PNG pages are stored as-is | If absent, downscaling is skipped and the original file is kept as-is (still subject to the size caps below). |

Composer packages used by export, already committed to `vendor/` (no extra
`composer install` step needed, but verify they exist after a fresh clone):
`setasign/fpdi`, `setasign/fpdf`, `phpoffice/phpword`, `phpoffice/math`
(`phpoffice/phpspreadsheet` is already a project dependency).

## Permissions

- `reports.view` — view reports, export PDF.
- `reports.export` — export permission (kept separate from `reports.view` for
  future-proofing; currently PDF/DOCX export routes are gated on `reports.view`).
- `reports.manage` — create/edit/regenerate/finalise/reopen/delete reports and
  section assets. Granted to the **Projects** role preset
  (`app/Support/RolePresets.php`) and retroactively backfilled onto any
  existing "Projects" role holders by migration
  `2026_09_19_000002_add_reports_manage_permission.php`.
- Report Data tab writes (Contract Particulars, Parties, Org Chart, Progress &
  Baseline, Delay Notices, Tests, Work Programme import, Report Images) use
  the existing `projects.edit` permission, not a `reports.*` permission.

After deploying, if a custom/non-preset role needs to manage monthly reports,
assign `reports.manage` to it manually (User Access / role management screen)
— presets are only applied when a role is first assigned to a user.

## Migrations

Report-related migrations added across Phases A–F
(`database/migrations/`, dated 2026-09-1x to 2026-09-2x):

```
2026_09_18_000001_add_report_cutoff_day_to_projects.php
2026_09_18_000002_add_contract_particulars_to_project_contracts.php
2026_09_18_000003_add_report_fields_to_project_parties.php
2026_09_18_000004_add_org_chart_fields_to_project_members.php
2026_09_18_000005_create_project_progress_tables.php
2026_09_18_000006_add_report_columns_to_project_invoices.php
2026_09_18_000007_add_report_group_to_correspondence_types.php
2026_09_18_000008_create_project_delay_notices_table.php
2026_09_18_000009_create_project_tests_table.php
2026_09_18_000010_add_is_tender_to_drawings.php
2026_09_18_000011_create_project_resource_categories_table.php
2026_09_18_000012_create_report_images_table.php
2026_09_19_000001_create_monthly_reports_tables.php
2026_09_19_000002_add_reports_manage_permission.php
2026_09_20_000001_add_file_meta_to_monthly_report_assets.php
2026_09_21_000001_create_programme_versions_and_activities_tables.php
2026_09_22_000001_add_overrides_at_to_monthly_report_sections.php
```

All run as part of the normal `php artisan migrate --force` step in
`deploy.sh` — no separate step required.

## Storage paths

All local disk, under `storage/app/`:

| Path | Contents |
|---|---|
| `monthly-reports/{report_id}/assets/` | Uploaded Gantt chart pages (PDF/PNG/JPG) and other section assets, written by `MonthlyReport\AssetService::store()`. |
| `programmes/{project_id}/` | Uploaded work-programme files (XLSX/XLS/CSV import staging, MSPDI XML import staging), written by `ReportData\ProgrammeVersionController`. |
| `projects/{project_id}/report-images/` | Report Images (site/location photos), written by `ReportData\ReportImageController::store()`. |

Ensure `storage/app/` is writable by the web server user and that
`php artisan storage:link` has been run (existing project requirement, not
new to this feature).

## Export limits

- PDF/DOCX export routes raise `set_time_limit(120)` and
  `ini_set('memory_limit', '512M')` for the duration of the request
  (`app/Http/Controllers/Api/MonthlyReportController.php`).
- Embedded image cap: 3 MB per image (`ReportViewData::MAX_IMAGE_BYTES`) —
  oversized images are skipped rather than embedded.
- Upload caps: attached page PDF/PNG/JPG 20 MB — covers section 2.5's Gantt
  chart pages and sections 2.2/2.4's uploaded S-curve chart pages; the
  separate chart-snapshot PNG kinds (`chart_physical_scurve`,
  `chart_financial_scurve`) cap at 2 MB (`MonthlyReportAssetController`);
  work-programme XLSX/XLS/CSV 10 MB, MSPDI XML 20 MB
  (`ReportData\ProgrammeVersionController`); report images 20 MB
  (`ReportData\ReportImageController`).
- Programme activity cap: 2,000 activities per programme
  (`ReportData\ProgrammeService`, enforced again as a row cap during XLSX
  import in `ProgrammeXlsxImporter::ROW_COUNT_CAP`).

## Known limitations

- **Word images are VML**, not modern DrawingML — this renders correctly in
  Word 2010 and later, but very old Word versions or some non-Microsoft
  viewers may not display them.
- **No cell rowspan support** in the DOCX writer — tables that would
  logically need a rowspan (e.g. section 3.1 header) are rendered with the
  header stacked instead.
- **Attached PDF pages are listed, not embedded**, in the Word export — a PDF
  page uploaded for section 2.5 (Gantt chart), 2.2 (physical S-curve) or 2.4
  (financial S-curve) cannot be inlined into a `.docx`; it is referenced as
  an attachment. Upload the chart page as PNG/JPG instead if it needs to
  appear inline in Word. In both the PDF and Word export, an uploaded chart
  page for 2.2/2.4 always takes priority over the auto-generated S-curve
  chart for that section — the generated SVG/PNG chart is only used as a
  fallback when no page has been uploaded.
- **Table of Contents does not auto-refresh** — Word shows stale page numbers
  until the field is updated (right-click the TOC → "Update Field", or press
  F9; Word may also prompt "Update fields?" when the document is first
  opened — answer Yes).
- **Reports snapshot data at generation time** — editing Report Data (Progress
  & Baseline, Parties, etc.) after a report section was generated does not
  retroactively change the report; click "Regenerate" on the affected
  section (or "Regenerate all") to pull in the latest system data. Saved
  edits (overrides) and notes are preserved across regeneration.
- **Free FPDI cannot read compressed PDF streams** — an attached PDF page
  upload (Gantt chart for 2.5, S-curve chart for 2.2/2.4) may fail with a
  "compression"-related error if the source PDF uses a compression filter the
  free FPDI build doesn't support. Re-save the PDF as PDF 1.4 (or "Print to
  PDF") before re-uploading, or upload a PNG/JPG image of the same page
  instead.

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| "Server Error" on any file upload (Report Images, Work Programme, Gantt page, report assets) | Missing `fileinfo` PHP extension | Enable `fileinfo` in cPanel PHP settings for the app's PHP version. |
| DOCX export returns HTTP 500 | Missing `zip` or `xml` extension | Verify both extensions are enabled; `mimes:` validation also throws without a MIME guesser — this codebase uses `extensions:` validation instead, so a fresh 500 here almost always points back to the missing extension, not validation. |
| PDF export is slow or times out | Large/oversized embedded images | Images over 3 MB are already skipped; if export is still slow, check for an unusually large number of images or an oversized Gantt PDF attachment. |

## Deploy steps

Standard deploy — no special steps beyond the usual:

```bash
git pull
bash deploy.sh   # runs `php artisan migrate --force` among other steps
```

After deploying, if a custom role (not one of the four presets) needs to
manage monthly reports, assign it the `reports.manage` permission manually.
