# SPEC-009 — append the 22 Sep Environment-module entry to MGE_PROGRESS.md

## Objective

Append one section to the end of `MGE_PROGRESS.md` recording what this session shipped, in the
voice and format of the existing log. **The text is provided verbatim below — paste it, add
nothing, remove nothing, rephrase nothing.** This is a transcription task, not a writing task.

## Context already verified on disk (if any of it is false, return BLOCKED)

- `MGE_PROGRESS.md` is 635 lines; the last line is the `### Batch 8 — Multi-site per project
  (project_sites) — SHIPPED` section's final `- ASSUMPTION:` bullet.
- Section headings in this file use `### Batch NN — Title — SHIPPED` followed by a one-line
  lead sentence and `- ` bullets, each bullet ending with test counts, commit hash and
  CI/deploy status, and `ASSUMPTION:` lines for unconfirmed decisions.
- Commit `6072f70f` (Environment module, 62 files, +6794/-236) and `01add380` (OLAK machinery
  import, another session's work) are both on `origin/main`; CI run `35720718237` and deploy run
  `35720847918` both completed `success`; the deploy log shows migrations 000008–000011 `DONE`.

## Files

- **EDIT** `MGE_PROGRESS.md` — append exactly one block at end of file.

## Constraints

- MUST NOT: modify, reorder or reword any existing line of the file. Append only.
- MUST NOT: add, remove or "tidy" any wording in the block below.
- MUST NOT: touch any other file; no `git add`/`commit`/`push`; no tests; no `npm run build`.
- ASK: if the file's last line differs from the description above, STOP and return BLOCKED with
  `tail -3 MGE_PROGRESS.md`.

## Verification

- [ ] `git diff --stat MGE_PROGRESS.md` → expect insertions only, 0 deletions. Quote it.
- [ ] `git diff MGE_PROGRESS.md | grep -c '^-[^-]'` → expect `0` (no removed lines). Quote it.
- [ ] `tail -5 MGE_PROGRESS.md` → quote; it must end with the `**Z9**` bullet.
- [ ] Paste the appended block's first and last line.

## Report

Write `.qwen/runs/RUN-009.md` and return a short message. Do not edit `.qwen/settings.json` or
any file outside `MGE_PROGRESS.md` and your own report.

## The block to append (verbatim, starting with a blank line after the current last line)

```
### Batch 9 — Environment module: monthly JPN-format report + water quality (22 Sep 2026) — SHIPPED
Built by the orchestrator/writer workflow (SPEC-006/007/008); two review rounds, first one REJECTed.
- Tables: `water_quality_records`, `environment_project_settings`, `environment_reports`,
  `environment_report_assets` — all four purely additive `Schema::create`, reversible, no touched rows.
- Water quality monitoring CRUD + a per-sample worksheet PDF; per-project environment settings
  (certified consultant, officers, policy and location images) with an upload/delete dialog.
- Monthly environment report: nine fixed sections (Contract Particular → Best Management Practice)
  pre-filled from the project, editable per section, regenerable, finalise-locked, and exported as
  **PDF** (DomPDF, page numbers stamped on the canvas) and **DOCX** — one shared `EnvironmentReportViewData`
  feeds both, mirroring MonthlyReport's `ReportViewData`.
- Frontend: `/environment/reports` list, editor, standalone viewer and `/environment/water-quality`;
  sidebar "Environmental" became an expandable "Environment" group. Bundle committed on purpose
  (`deploy.sh` has no npm step).
- 29 feature tests. Suite: 660 passing. Commits `6072f70f` + `01add380`. CI + deploy green, migrations
  000008–000011 `DONE` on production.

**Three defects a review caught that the first pass shipped with:**
1. `report_no` was derived from `max()` *through* the `SoftDeletes` scope — delete the newest report and
   the next create re-issues the same number, the composite unique index rejects it, and **report creation
   stays broken for that project forever** with no restore path in the module. Fixed with `withTrashed()`
   and the whole create moved inside one `lockForUpdate()` transaction.
2. The finalise lock covered `update`/`regenerate` but **not** the asset endpoints or deletion, so a
   signed report's BMP photos, consultant certificate and policy/location images could be swapped or
   destroyed after finalisation while `finalised_at` and the signature block stayed intact.
3. `EnvironmentReportPdfExporter` rendered `pdf.environment-report.layout`, a view that was never written
   (the previous session ran out of context mid-split), so **every PDF export was a 500** and the DOCX
   path — which builds its own document — hid it. Now one document shell, `body.blade.php` demoted to a
   fragment, the dead `cover-page.blade.php` deleted, and the test asserts the bytes start `%PDF-`.

**🔴 The test runner was destroying the developer database — fixed, and the fix is load-bearing.**
`phpunit.xml` cannot pin the test database. This machine's shell exports `DB_CONNECTION=mysql` /
`DB_DATABASE=mge_pms`, PHPUnit's `<env … force="true">` does **not** override a variable already in the
real environment, and Laravel's `.env` loader does not override what is already set either — so
`RefreshDatabase` ran `migrate:fresh` on the **dev** database. It is now empty of user data (schema
rebuilt, rows gone). `tests/TestCase::setUp()` forces `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:`
through `putenv()` + `$_ENV` + `$_SERVER` **before** `parent::setUp()` — placement is the whole point,
because `migrate:fresh` runs *inside* `parent::setUp()`, so a guard after it can only report the
destruction, never prevent it (that is exactly what round 1 of this fix got wrong). Proof after the fix:
the hostile-env command that used to wipe the DB now passes, and `information_schema.TABLES.CREATE_TIME`
for `mge_pms` is unchanged. **Anyone adding a new test DB assumption must keep that guard.**
Dev data was restored from the 7 Sep `mge_prod_snapshot` (data-only import, 82 shared tables, `migrations`
and the transient tables excluded), then `RolePresets::backfillDirectPermissions()` so dev logins carry
today's permissions: 37 users, 2 projects, 14 employees, 10 users holding `environmental.view`.

**Open, not fixed (deliberate):** stored `sections` JSON is shape-unvalidated, so `PUT
{"sections":{"results":"x"}}` persists and then 500s every later export of that report; changing a
report's period does not re-derive `parameters`/`results`; there is no per-project authorization
anywhere in the codebase (this module matches house practice, it does not regress it); the water-quality
worksheet prints river conditions and in-situ only — **COD/BOD/TSS/E.coli never appear on the worksheet**
although section 8.0 of the report shows them. **Z9: the worksheet is assumed to be the field-sampling
form, not the lab-result form — needs Rahim to confirm before section 8.0 and the worksheet are aligned.**
```
