# Monthly Progress Report — Phase E: Work Programme Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Section 2.5 (Actual Work Progress) is filled from an imported work programme: users upload the MS Project view exported to Excel (with a column-mapping step) or an MS Project XML (MSPDI) file into a versioned programme per project; the report renders the full activity table (landscape, small font, indented outline, summary rows bold) in both PDF and DOCX, followed by the Gantt pages from Phase C.

**Architecture:** Two new tables (`project_programme_versions`, `programme_activities`) owned by `ProgrammeService`. Two pure importers produce a normalised activity list: `ProgrammeXlsxImporter` (PhpSpreadsheet, already in `vendor/` via maatwebsite/excel) with `preview()` → headers/sample/suggested mapping and `import()` with a user-confirmed mapping; `ProgrammeMspdiImporter` (SimpleXML). Outline level comes from a mapped "Outline Level" column, else from leading whitespace in the name; `is_summary` is derived (a row is a summary when the next row is deeper). `WorkProgrammeBuilder` snapshots the project's **current** version into section 2.5 `rows`; the PDF/DOCX 2.5 renderers gain indent/bold; the editor shows the table read-only (450-row overrides are out of scope — edit the programme in Report Data). A **Work Programme** panel joins the Report Data tab.

**Tech Stack:** Laravel 12, PhpSpreadsheet (vendor), SimpleXML, DomPDF, PHPWord, React 19, PHPUnit, Pint.

**Spec:** `docs/superpowers/specs/2026-09-17-monthly-progress-report-design.md` §3.6, §2 (row 2.5), §6 (programme routes), §7 (Report Data tab), §8 Phase E.

## Global Constraints

- Production PHP lacks `fileinfo` → uploads validated with `extensions:xlsx,xls,csv` / `extensions:xml`; never `mimes:`, `guessExtension()`, `getMimeType()`, `Storage::response()`. Store with `storeAs()` + UUID names on disk `local` under `programmes/{project_id}/`.
- Permissions follow the other Report Data resources: read `projects.view`, write `projects.edit`; every nested resource resolved through `{project}` (`where('project_id', …)->findOrFail`) → 404 otherwise.
- Importers are pure (no DB); the service persists inside `DB::transaction()`. Import replaces nothing silently: each import creates a **new version**; the newest imported version becomes current unless the user unticks "Set as current".
- Activity cap 2,000 rows per version (422 above); preview reads at most 50 rows; sample shows 5.
- Dates parsed from Excel serials, `d/m/Y`, `Y-m-d`, `d-M-y`, and ISO datetimes; unparsable → null (never abort the import); percentages accept `45`, `45%`, `0.45` (≤ 1 with a `%`-less header treated as a fraction only when *all* values ≤ 1).
- Builders read only; `WorkProgrammeBuilder` picks the version flagged current, else the latest by `status_date` then `id`; when none exists it returns the Phase B placeholder note.
- PDF 2.5 stays in the landscape default set; table 6.5pt with a repeating `<thead>`; DOCX table `repeatHeader` and 6pt; indent = `outline_level − 1` × 3 mm (PDF) / 0.3 cm (DOCX); summary rows bold.
- Local DB: never `migrate:fresh/refresh/rollback/db:wipe`; migrations additive.
- `public/build/**` committed with every `resources/js` change.
- Lint/test: `php vendor/bin/pint --test`, `php -d memory_limit=2G vendor/bin/phpunit`, `npm run build`.
- Commit locally on `feature/monthly-report-phase-e` (from `main` @ 7bd4f4e); **no push** until the user approves.

---

## File structure

| Path | Responsibility |
|---|---|
| `database/migrations/2026_09_21_000001_create_programme_tables.php` | `project_programme_versions`, `programme_activities` |
| `app/Models/ProjectProgrammeVersion.php`, `app/Models/ProgrammeActivity.php` | Eloquent models |
| `app/Services/ReportData/ProgrammeService.php` | create version + activities, set current, delete (+file), list |
| `app/Services/ReportData/Programme/ProgrammeXlsxImporter.php` | `preview(path)`, `import(path, mapping)` → activities array |
| `app/Services/ReportData/Programme/ProgrammeMspdiImporter.php` | `import(path)` → activities array |
| `app/Services/ReportData/Programme/ActivityNormaliser.php` | dates/percent/duration parsing, outline inference, summary derivation |
| `app/Http/Controllers/Api/ReportData/ProgrammeVersionController.php` | REST + preview/import endpoints |
| `app/Services/MonthlyReport/Sections/WorkProgrammeBuilder.php` | real builder |
| `resources/views/pdf/monthly-report/sections/s2-5.blade.php` | activity table rendering |
| `app/Services/MonthlyReport/Export/Docx/Writers/GanttWriter.php` | activity table in DOCX (indent/bold) |
| `resources/js/services/reportDataService.js` | programme methods |
| `resources/js/pages/projects/report-data/WorkProgrammePanel.jsx` (+ `programme/ImportWizard.jsx`, `programme/ActivitiesTable.jsx`) | panel |
| `resources/js/pages/reports/editor/TextSection.jsx` or new `ProgrammeSection.jsx` | 2.5 read-only table + link |
| `tests/Unit/ReportData/{ActivityNormaliserTest,ProgrammeXlsxImporterTest,ProgrammeMspdiImporterTest}.php`, `tests/Feature/ReportData/ProgrammeApiTest.php`, `tests/Feature/MonthlyReport/WorkProgrammeSectionTest.php`, `tests/fixtures/programme/{sample.xlsx generator, sample-mspdi.xml}` | tests |

---

### Task 1: Tables, models, `ProgrammeService`

**Files:** migration, two models, `app/Services/ReportData/ProgrammeService.php`, `tests/Feature/ReportData/ProgrammeServiceTest.php`

**Interfaces:**
- Migration:
  ```php
  Schema::create('project_programme_versions', function (Blueprint $t) {
      $t->id();
      $t->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
      $t->string('label', 120);
      $t->date('status_date')->nullable();
      $t->string('source_type', 20);           // xlsx | msproject_xml
      $t->string('source_file_path')->nullable();
      $t->string('source_file_name')->nullable();
      $t->boolean('is_current')->default(false);
      $t->unsignedInteger('activity_count')->default(0);
      $t->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
      $t->timestamps();
      $t->index(['project_id', 'is_current']);
  });
  Schema::create('programme_activities', function (Blueprint $t) {
      $t->id();
      $t->foreignId('version_id')->constrained('project_programme_versions')->cascadeOnDelete();
      $t->unsignedInteger('seq');
      $t->unsignedTinyInteger('outline_level')->default(1);
      $t->string('name', 255);
      $t->unsignedInteger('duration_days')->nullable();
      $t->date('start')->nullable();
      $t->date('finish')->nullable();
      $t->decimal('actual_pct', 5, 2)->nullable();
      $t->decimal('plan_pct', 5, 2)->nullable();
      $t->boolean('is_summary')->default(false);
      $t->index(['version_id', 'seq']);
  });
  ```
- `ProgrammeService`:
  - `createVersion(int $projectId, array $meta, array $activities, ?int $userId): ProjectProgrammeVersion` — `meta = ['label', 'status_date', 'source_type', 'source_file_path', 'source_file_name', 'set_current' => true]`; inserts activities in chunks of 500 with `seq` 1..n; when `set_current` → clears `is_current` on siblings; throws `ValidationException` when `count($activities) > 2000` or `=== 0`.
  - `setCurrent(ProjectProgrammeVersion $v): void`, `update(ProjectProgrammeVersion $v, array $attrs)` (label/status_date), `delete(ProjectProgrammeVersion $v): void` (deletes file; if it was current, promote the latest remaining by status_date/id), `currentFor(int $projectId): ?ProjectProgrammeVersion` (current flag else latest).
  - Activity array shape (shared by importers): `['name' => string, 'outline_level' => int≥1, 'duration_days' => ?int, 'start' => ?string Y-m-d, 'finish' => ?string, 'actual_pct' => ?float, 'plan_pct' => ?float, 'is_summary' => bool]`.

- [ ] Failing tests: create version marks current + clears siblings; delete current promotes latest; >2000 → 422; zero → 422; `currentFor` fallback.
- [ ] Implement; `php artisan migrate` (additive) locally; PASS; commit `"Add programme versions and activities"`.

---

### Task 2: Importers + normaliser

**Files:** `ActivityNormaliser.php`, `ProgrammeXlsxImporter.php`, `ProgrammeMspdiImporter.php`, `tests/Unit/ReportData/*`, fixture `tests/fixtures/programme/sample-mspdi.xml` (hand-written, 8 tasks with OutlineLevel 1–3, `Duration` like `PT40H0M0S`, `PercentComplete`, one `Summary=1`), XLSX fixtures generated in the test with PhpSpreadsheet (`\PhpOffice\PhpSpreadsheet\Spreadsheet`).

**Interfaces:**
- `ActivityNormaliser`:
  - `static date(mixed $v): ?string` — Excel serial (numeric 20000–80000 → `\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject`), `d/m/Y`, `d/m/y`, `Y-m-d`, `d-M-y`, `d M Y`, ISO 8601 datetime; else null.
  - `static percent(mixed $v, bool $fractionMode): ?float` — strips `%`, comma→dot; fraction mode multiplies by 100; clamps 0–100; null when not numeric.
  - `static durationDays(mixed $v): ?int` — `12`, `12 days`, `12d`, `1.5 wks`→8, `PT96H0M0S` (ISO from MSPDI; hours/8 rounded), `0` allowed; null otherwise.
  - `static outlineFromIndent(string $name, int $unit): array{level:int, name:string}` — level = 1 + floor(leadingSpaces/unit) (tabs count as `unit`), trimmed name; `static inferIndentUnit(array $names): int` — smallest non-zero leading-space count across names (default 2).
  - `static deriveSummaries(array $activities): array` — sets `is_summary = true` where the next row's `outline_level` is greater.
- `ProgrammeXlsxImporter`:
  - `preview(string $absolutePath): array` → `['headers' => string[], 'sample' => array<int, string[]> (5 rows), 'row_count' => int (data rows, capped scan 2,050), 'suggested' => ['name' => ?int, 'duration' => ?int, 'start' => ?int, 'finish' => ?int, 'actual_pct' => ?int, 'plan_pct' => ?int, 'outline_level' => ?int]]` — suggestions by case-insensitive header keywords: name ⇐ `name|task|activity|description`; duration ⇐ `duration|dur`; start ⇐ `start`; finish ⇐ `finish|end`; actual_pct ⇐ `% complete|percent complete|actual|physical`; plan_pct ⇐ `plan|planned|scheduled`; outline_level ⇐ `outline level|level|wbs level`. Header row = first row with ≥ 2 non-empty cells. Uses `IOFactory::createReaderForFile` with `setReadDataOnly(true)`; `.csv` allowed.
  - `import(string $absolutePath, array $mapping): array` — `mapping` = column indexes (0-based) for the keys above (`name` required; others nullable); skips rows with empty name; outline from mapped column when present and numeric, else from indent; percent fraction mode auto-detected per column (all values ≤ 1 and header has no `%`); returns normalised activities with `deriveSummaries` applied; throws `ValidationException` (`file`) when the name column is missing/empty for every row.
- `ProgrammeMspdiImporter::import(string $absolutePath): array` — `simplexml_load_file` with `LIBXML_NONET`, namespace-agnostic (`$xml->Tasks->Task` — handle the default MSPDI namespace with `children()`), skip `UID 0` (project summary) and rows with empty Name; `OutlineLevel`, `Duration` via `durationDays`, `Start`/`Finish` via `date`, `PercentComplete` → actual_pct, `Summary` → is_summary (and still `deriveSummaries` for safety), `plan_pct` null; invalid XML → `ValidationException` "The file is not a valid MS Project XML export."

- [ ] Failing unit tests (each parser branch, suggestions, indent inference, fraction detection, MSPDI fixture: 8 tasks, levels, one summary, duration 5 days from `PT40H0M0S`) → implement → PASS → commit `"Add work programme XLSX and MSPDI importers"`.

---

### Task 3: API

**Files:** `ProgrammeVersionController.php`, `routes/api.php`, `tests/Feature/ReportData/ProgrammeApiTest.php`

**Routes** (inside the existing `projects/{project}` report-data area, `auth:sanctum`):
```
GET    /projects/{project}/programme-versions                       projects.view  → list [{id,label,status_date,source_type,source_file_name,is_current,activity_count,imported_by_name,created_at}]
POST   /projects/{project}/programme-versions/preview               projects.edit  multipart file (extensions:xlsx,xls,csv, max 10240) → stores to programmes/{project}/tmp-{uuid}.{ext}, returns preview + `token` (the stored relative path, signed: return `token = Crypt::encryptString($path)`)
POST   /projects/{project}/programme-versions/import                projects.edit  {token, mapping{name,duration,start,finish,actual_pct,plan_pct,outline_level}, label (required, max 120), status_date (nullable date), set_current (bool, default true)} → decrypt token, verify it starts with programmes/{project}/tmp-, import, move file to programmes/{project}/{uuid}.{ext}, createVersion → 201 version
POST   /projects/{project}/programme-versions/import-mspdi          projects.edit  multipart file (extensions:xml, max 20480), label, status_date, set_current → 201
PUT    /projects/{project}/programme-versions/{version}             projects.edit  {label?, status_date?, is_current?(true only)}
DELETE /projects/{project}/programme-versions/{version}             projects.edit
GET    /projects/{project}/programme-versions/{version}/activities  projects.view  paginated 100/page (per_page ≤ 500) [{seq,outline_level,name,duration_days,start,finish,actual_pct,plan_pct,is_summary}]
```
- Tests: preview returns headers/suggested for a generated xlsx; import with mapping creates version + activities and marks current; token from another project → 422/404; MSPDI import; version of another project → 404; delete current promotes; view-only user 403 on writes; activities pagination.

- [ ] Failing tests → implement → PASS → commit `"Add work programme import API"`.

---

### Task 4: Section 2.5 builder + PDF + DOCX

**Files:** `WorkProgrammeBuilder.php`, `s2-5.blade.php`, `GanttWriter.php` (+ `SectionSpecs` 2.5 columns), `tests/Feature/MonthlyReport/WorkProgrammeSectionTest.php`, extend `PdfExportTest`/`DocxExportTest`.

**Interfaces:**
- Builder output: `['schema' => 1, 'version' => ['id', 'label', 'status_date'] | null, 'note' => ?string (placeholder note only when no version), 'rows' => [['no' => seq, 'task' => name, 'level' => outline_level, 'summary' => bool, 'duration' => "12 days" | '', 'start' => 'd/m/Y' | '', 'finish' => …, 'actual' => "45%" | '', 'plan' => "50%" | '']]]`.
- PDF `s2-5.blade.php`: caption line "Programme: {label} (status date {d/m/Y})" when version; table `<thead>` No. | Task | Duration | Start | Finish | Actual % | Plan % (6.5pt landscape, 8pt portrait), `padding-left: {(level-1)*3}mm` on Task, `<strong>` for summary rows, `page-break-inside: auto` on the table (rows may split across pages), then the existing Gantt "attached on the following N page(s)" line.
- DOCX `GanttWriter`: same caption; `table()` with `fontSize 6`, `repeatHeader`, cell text indent via leading NBSPs (`str_repeat("\u{00A0}", (level-1)*4)`) or a per-cell `indent` option added to `DocxDocument::table` (preferred: add `cellStyle` callback support `fn($r,$c) => ['indent' => twips, 'bold' => bool]`); summary rows bold; then Gantt pages as today.
- Editor: `sectionConfig` `'2.5': {type: 'gantt'}` stays; the `gantt` editor renders the rows read-only (`ActivitiesTable`-like list, indent + bold) above the Gantt panel with a link "Manage the work programme in Report Data › Work Programme" (to `/projects/{id}`); no overrides for rows (Reset/Save not offered for 2.5 rows; notes still editable).

- [ ] Failing tests (builder picks current version, formats, placeholder when none; PDF html contains the caption + a task name + `padding-left`; DOCX contains the task and repeatHeader) → implement backend → PASS → commit `"Render the imported work programme in section 2.5"` (frontend part of this task is folded into Task 5 to keep one build).

---

### Task 5: Frontend — Work Programme panel, import wizard, editor 2.5, Panduan

**Files:** `reportDataService.js` (`listProgrammeVersions(pid)`, `previewProgrammeXlsx(pid, file)`, `importProgrammeXlsx(pid, payload)`, `importProgrammeMspdi(pid, file, meta)`, `updateProgrammeVersion(pid, id, data)`, `deleteProgrammeVersion(pid, id)`, `listProgrammeActivities(pid, id, params)`), `WorkProgrammePanel.jsx`, `programme/ImportWizard.jsx`, `programme/ActivitiesTable.jsx`, `ReportDataTab.jsx` (add tab "Work Programme" after "Progress & Baseline"), editor `sectionConfig.js`/`TextSection.jsx`/`GanttAssetsPanel.jsx` host for the 2.5 read-only table, `panduan/content.js`.

**Behaviour:**
- Panel: versions table (label, status date, source, activities, current badge, imported by/date) with actions **View**, **Set current**, **Rename/date**, **Delete** (`useConfirm`); buttons **Import Excel** and **Import MS Project XML**.
- Import wizard (Excel): step 1 file input (`accept=".xlsx,.xls,.csv"`) → `preview` → step 2 mapping selects per field pre-filled from `suggested` (name required), sample table (5 rows) with the chosen columns highlighted, label (default file name without extension), status date, "Set as current" checkbox → **Import** → toast + refresh + auto-open View. Errors from 422 shown inline.
- MSPDI: single dialog (file `accept=".xml"`, label, status date, set current).
- View: paginated activities table (100/page) with indent by level and bold summaries; sticky header; search box filters by name client-side within the page (keep simple).
- Editor 2.5: read-only rows table (first 100 rows + "… and N more rows — the full table is in the export") with the link to Report Data; Gantt panel below unchanged.
- Panduan (Sistem › Projek › Report Data): sub-steps for Work Programme: eksport view MS Project ke Excel (Task Name, Duration, Start, Finish, % Complete, [Plan %], [Outline Level]) → Import Excel → padankan lajur → Import; atau simpan sebagai XML (MSPDI) → Import MS Project XML; versi terkini ("current") digunakan dalam laporan 2.5; Gantt masih dimuat naik sebagai PDF/PNG di editor laporan.

- [ ] Implement; `npm run build`; commit source + build `"Add the Work Programme panel and import wizard"`.

---

### Task 6: Local demo + full verification

- Local only (git-excluded `ReportDataDemoSeeder`): generate a demo XLSX with PhpSpreadsheet (30 activities, 3 outline levels, dates across the project, % complete) and import it via `ProgrammeXlsxImporter` + `ProgrammeService::createVersion` as "Draft CPM Rev 0" (idempotent: delete existing versions for project 3 first, files included); then regenerate report No.3 (`MonthlyReportService::regenerate`) so 2.5 has rows.
- Verification: `pint --test`, full `phpunit`, `npm run build`, seeder twice, regenerate `storage/app/mpr-demo-no3.pdf` and `.docx`, inspect the 2.5 pages (PDF via Read, DOCX via `textutil`).
- Commit nothing new unless verification exposes a defect (then a fix commit).

---

## Self-review

**Spec coverage (§8 row E):** XLSX mapping importer (T2/T3/T5), MSPDI importer (T2/T3/T5), activity table section in PDF/DOCX (T4), Report Data screen (T5), routes (§6) (T3). Gantt rendering from data stays out of scope (§3.6).

**Placeholder scan:** all tasks name concrete files, signatures, columns and test cases; the only prose-only pieces are UI behaviours with explicit field lists.

**Type consistency:** activity array shape defined once (T1) and consumed by T2/T3/T4; builder row keys (`no, task, level, summary, duration, start, finish, actual, plan`) match the existing 2.5 Blade/DOCX column keys from Phases B–D plus `level`/`summary`; service names in T5 match T3 routes.
