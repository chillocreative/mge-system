# Monthly Progress Report — Phase F: Polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the spec's Phase F items that are still open — diff badges (system data changed since the user edited), empty-state guidance that points to where each section's data is entered, deep links into project tabs/panels — and pay down the hardening items deferred during Phases B–E, plus a deployment note.

**Architecture:** A nullable `overrides_at` timestamp on `monthly_report_sections` (set whenever non-empty overrides are saved) lets the API expose `stale = regenerated_at > overrides_at` per section; the editor renders a **"System data changed"** badge/banner with two actions that already exist (Reset to system data / re-save to keep edits). Empty-state guidance is a static map `SECTION_SOURCES[key] → {label, to}` in the editor pointing at the project page with `?tab=…&panel=…`, which `ProjectDetail`/`ReportDataTab` learn to honour. Hardening: import file move-ordering, preview-token expiry + tmp sweep, `lockForUpdate` on the current-version flip, `Project::findOrFail` on list routes. A short `docs/MONTHLY-REPORT-DEPLOY.md` records the production prerequisites.

**Tech Stack:** Laravel 12, React 19 + React Router 7, PHPUnit, Pint.

**Spec:** `docs/superpowers/specs/2026-09-17-monthly-progress-report-design.md` §3.14 (design rule: diff badge), §7 (diff badges, empty-state), §8 Phase F, §10 risks table.

## Global Constraints

- Migrations additive and idempotent (`Schema::hasColumn` guards); never `migrate:fresh/refresh/rollback/db:wipe` locally.
- Production PHP lacks `fileinfo` — no new MIME sniffing; keep `extensions:` rules.
- Permission model unchanged (`reports.view/manage`, `projects.view/edit`); nested resources scoped by project/report.
- `public/build/**` committed with every `resources/js` change.
- Deep links must be backward compatible: `/projects/{id}` without params behaves exactly as today.
- Lint/test: `php vendor/bin/pint --test`, `php -d memory_limit=2G vendor/bin/phpunit`, `npm run build`.
- Commit locally on `feature/monthly-report-phase-f` (from `main` @ 92b1c37); **no push** until the user approves.

---

## File structure

| Path | Responsibility |
|---|---|
| `database/migrations/2026_09_22_000001_add_overrides_at_to_monthly_report_sections.php` | `overrides_at` timestamp nullable |
| `app/Models/MonthlyReportSection.php`, `app/Services/MonthlyReport/MonthlyReportService.php`, `app/Http/Controllers/Api/MonthlyReportController.php` | set `overrides_at`; expose `stale` |
| `app/Http/Controllers/Api/ReportData/ProgrammeVersionController.php`, `app/Services/ReportData/ProgrammeService.php` | hardening |
| `resources/js/pages/reports/editor/sectionSources.js`, `MonthlyReportEditor.jsx`, editor components | badges, banner, empty-state guidance |
| `resources/js/pages/projects/ProjectDetail.jsx`, `report-data/ReportDataTab.jsx` | `?tab=` / `?panel=` deep links |
| `docs/MONTHLY-REPORT-DEPLOY.md`, `resources/js/pages/panduan/content.js` | deployment note; guide touch-ups |
| tests under `tests/Feature/MonthlyReport`, `tests/Feature/ReportData` | coverage |

---

### Task 1: Stale-override tracking (backend)

**Files:** migration; `MonthlyReportSection.php` (`$casts['overrides_at'] = 'datetime'`, `$fillable`); `MonthlyReportService::saveSection` (when `overrides` present and non-empty → `overrides_at = now()`; when overrides cleared/null → `overrides_at = null`); `MonthlyReportService::regenerate` unchanged (already sets `regenerated_at`); `MonthlyReportController::present()` adds per section `'stale' => $s->overrides && $s->overrides_at && $s->regenerated_at && $s->regenerated_at->gt($s->overrides_at)`; `duplicateStatic` copies `overrides_at` too.
**Tests** (`MonthlyReportApiTest`): save overrides → `stale=false`; regenerate that section → `stale=true`; re-save the same overrides → `stale=false`; reset (`overrides: {}`) → `overrides_at` null, `stale=false`; a section without overrides is never stale.

- [ ] Failing tests → implement → `php artisan migrate` locally → PASS → commit `"Track when section overrides were last saved"`.

---

### Task 2: Import/programme hardening (backend)

**Files:** `ProgrammeVersionController.php`, `ProgrammeService.php`, tests.

1. **File/DB ordering**: in `import()` and `importMspdi()`, move the upload to its permanent path *first*, then `createVersion()`; on any exception delete the permanent file (and the tmp if still present) and rethrow. Tests: >2000 rows → 422 and no files left; success → file exists at `source_file_path`.
2. **Preview token expiry**: encrypt a JSON payload `{path, file_name, issued_at}`; `import()` rejects tokens older than 24 h with 422 "The preview has expired — upload the file again." Test with a token forged via `Crypt::encryptString(json_encode([... 'issued_at' => now()->subDays(2)->timestamp]))`.
3. **Tmp sweep**: at the start of `preview()`, delete `programmes/{project}/tmp-*` files older than 24 h (`Storage::disk('local')->files()` + `lastModified()`); test creates an old tmp file (touch mtime via `Storage::disk('local')->put` then `touch(Storage::path(), time()-90000)`) and asserts it's gone after a preview.
4. **Current-version flip**: `ProgrammeService::createVersion`/`setCurrent` use `lockForUpdate()` on the project's versions inside the transaction (test: after two sequential creates with `set_current`, exactly one current — already covered; add the lock and keep tests green).
5. **`index()`/`activities()`**: `Project::findOrFail($projectId)` → 404 for unknown projects (test).

- [ ] Failing tests → implement → PASS → commit `"Harden work programme import file handling and tokens"`.

---

### Task 3: Deep links into project tabs and Report Data panels (frontend)

**Files:** `ProjectDetail.jsx` (read `useSearchParams`: `tab` ∈ known tab ids → initial `activeTab`; keep state-driven switching, and update the URL param on tab click with `setSearchParams({tab}, {replace: true})` so refresh keeps the tab), `ReportDataTab.jsx` (accept `initialPanel` prop from `ProjectDetail` via `searchParams.get('panel')`; unknown → first panel), `WorkProgrammePanel`/others unchanged.
**Interfaces:** `/projects/{id}?tab=report-data&panel=programme`, `?tab=site-logs`, `?tab=calendar`, etc. Tab ids: `overview, report-data, tasks, milestones, timeline, site-logs, sites, documents, calendar, discussions`; panel ids: `particulars, parties, org-chart, progress, programme, registers, categories, images`.

- [ ] Implement; `npm run build`; commit `"Support tab and panel deep links on the project page"`.

---

### Task 4: Diff badges + empty-state guidance (frontend)

**Files:** `editor/sectionSources.js` (new), `MonthlyReportEditor.jsx`, `editor/*` where the empty state renders, `services/monthlyReportService.js` (no change expected).

**Interfaces:**
- `SECTION_SOURCES = { '1.1': {label: 'Contract Particulars', to: p => `/projects/${p}?tab=report-data&panel=particulars`}, '1.2': parties, '1.3': images, '1.4': org-chart, '1.5': org-chart, '2.1'|'2.2'|'2.4': progress, '2.3': {label: 'Claims (Contracts)', to: p => `/projects/contracts`}, '2.5': programme, '2.6': registers, '3.1'|'3.2': {label: 'Correspondence', to: () => '/projects/correspondence'}, '3.4': registers, '3.6': {label: 'Drawings (Documents)', to: p => `/projects/${p}?tab=documents`}, '3.7': {label: 'Calendar', to: p => `/projects/${p}?tab=calendar`}, '4.1'|'4.2'|'4.3': {label: 'Site Logs', to: p => `/projects/${p}?tab=site-logs`}, '5.0': images }`.
- Empty state: when a section's merged data has no rows/values (`rows` empty, or `placeholder`, or `images` empty …) the editor shows a card: "No data yet — enter it under **{label}**, then click *Regenerate this section*." with a `<Link>` (opens in the same tab; the shell already persists drafts on navigation? no — warn via the existing dirty guard if any, else just navigate).
- Diff badge: left nav shows an amber "changed" pill when `section.stale`; the section pane shows a banner "System data changed since you edited this section (regenerated {relative time}). Your edits are kept. [Use system data] [Keep my edits]" — *Use system data* = existing reset (`overrides: {}`), *Keep my edits* = re-save the current overrides unchanged (`saveSection(id, key, {overrides})`, which refreshes `overrides_at`) → banner disappears. Also mark stale in the "Save all" flow: nothing special.
- `applyReport`/`applyReportKeepingDrafts` keep `stale` from the server.

- [ ] Implement; `npm run build`; commit `"Show stale-override badges and empty-state guidance in the report editor"`.

---

### Task 5: Deployment note, Panduan touch-ups, verification

**Files:** `docs/MONTHLY-REPORT-DEPLOY.md` (new: PHP extensions required — `zip`, `xml`, `dom`, `fileinfo` (uploads), optional `gd` (PNG Gantt/photos); permissions `reports.view/manage` (Projects preset), `projects.edit` for Report Data; migrations list from Phases A–F; storage paths under `storage/app/{monthly-reports,programmes,report-images}`; export limits (memory 512M, 120 s, 3 MB image cap, 2,000 activities); known limitations (VML images in Word, no rowspan, Gantt PDF not embedded in Word, TOC refresh with F9); troubleshooting: "Server Error on upload → fileinfo"), `CLAUDE.md` (one line pointing to the note under Notes), `panduan/content.js` (mention "Update fields?" prompt when opening Word; diff badge meaning; empty-state links).
**Verification:** `pint --test`, full `phpunit`, `npm run build`; local: run `ReportDataDemoSeeder` twice; regenerate demo PDF/DOCX; sanity-check `stale` by saving an override on 1.2 then regenerating via tinker/API and reading `present()`.

- [ ] Commit `"Document monthly report deployment and finish the editor guide"` (docs, CLAUDE.md, content.js, build).

---

## Self-review

**Spec coverage (§8 row F):** duplicate-from-previous and finalise/lock shipped in Phase B; diff badges → T1/T4; empty-state guidance → T3/T4; Panduan → T5. Deferred hardening from the ledgers → T2.

**Placeholder scan:** all tasks list exact files, columns, payloads, tests.

**Type consistency:** `stale` produced by `present()` (T1) consumed by the editor (T4); deep-link params (`tab`, `panel`) produced by `sectionSources.js` (T4) and honoured by `ProjectDetail`/`ReportDataTab` (T3); token payload shape (T2) internal to the controller.
