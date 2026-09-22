# REVIEW-006 — SPEC-006 + SPEC-007 (Environment module: PDF layout, review fixes)

**Reviewer:** orchestrator · **Date:** 2026-09-22 18:20 (+08) · **Baseline:** `a298b470` + uncommitted Environment module
**Work orders:** `.qwen/specs/SPEC-006-environment-report-pdf-layout.md`, `.qwen/specs/SPEC-007-environment-review-fixes.md`

### Verdict: REJECT

Round 1 of 3. Items 1-7 of SPEC-007 and all of SPEC-006 are accepted; **item 0 (the database
safety net) does not work as written, and I have the timestamps to prove it.** One structural
deviation on item 1. Harness config was edited outside the writer's mandate.

---

## Accepted

**SPEC-006 (PDF layout).** `resources/views/pdf/environment-report/layout.blade.php` exists and
is the single document shell; `body.blade.php` is a fragment (verified: 8 `page-break` divs, the
9 section includes in the original order, header/toc intact); the dead `cover-page.blade.php` is
gone; `SECTION_SUBITEMS` is now the only source of TOC sub-items for both PDF and DOCX with the
values that printed before the change. `EnvironmentReportExportTest` 4/4, and the added
`%PDF-` + >5000-byte assertion is a real content check, not a status-code rubber stamp.

**SPEC-007 items 2, 3, 4, 6, 7.** Read and confirmed in source:
`guardNotFinal()` (`EnvironmentReportService.php:194`) is applied to `update` (`:69`),
`regenerate` (`:103`), `delete` (`:127`, before the asset-file deletes run), `addAsset` (`:140`),
`updateAsset` (`:153`), `removeAsset` (`:173`) and asset reorder (`:188`) — the finalised lock now
covers the evidence, matching `MonthlyReport/AssetService`. Asset endpoints resolve through
`whereHas('report', fn ($q) => $q->withoutTrashed())->findOrFail()` (controller `:165,181,191`),
so a soft-deleted report's files are no longer addressable, and no route URI changed (frontend
untouched). `update` regained `after_or_equal:period_start` (`:58`), `sort_order` gained
`max:65535` (`:169`) to fit `unsignedSmallInteger`, and the settings controller rejects an
unknown project before touching storage (`:18,30,55,88,119`) with `uploadImage` now
store → save → delete-old, so a failed write can no longer destroy the previous image.

## Rejected

### R1 — BLOCKER: `force="true"` does not override the developer's shell, and the guard fires after the wipe

SPEC-007 item 0 required proof: `DB_CONNECTION=mysql DB_DATABASE=mge_pms php artisan test
tests/Feature/MonthlyReport/RegisterSectionsTest.php` must **pass**. `RUN-007.md` never ran that
command — it ran the same file with `env -u DB_CONNECTION …`, which is the already-safe path and
proves nothing about the hazard. I ran the required command myself:

```
$ DB_CONNECTION=mysql DB_DATABASE=mge_pms php artisan test tests/Feature/MonthlyReport/RegisterSectionsTest.php
  Tests:    7 failed (0 assertions)          <- RuntimeException: Tests must use sqlite, not mysql.
```

It fails, so `config('database.default')` was still `mysql` — PHPUnit's `<env force="true">` did
not win against the real process environment as seen by Laravel's config loader. And the guard
did not prevent the damage either:

```
$ mysql -h 127.0.0.1 -u root -e "SELECT TABLE_NAME, CREATE_TIME FROM information_schema.TABLES
    WHERE TABLE_SCHEMA='mge_pms' AND TABLE_NAME IN ('users','correspondence_types');"
  correspondence_types  2026-09-22 18:12:22
  users                 2026-09-22 18:12:21        <- recreated by that 18:12 test run
```

`RefreshDatabase` runs `migrate:fresh` inside `parent::setUp()`. A guard placed **after**
`parent::setUp()` can only report the wipe, never prevent it. The dev database
(`mge_pms`) has now been `migrate:fresh`-ed repeatedly today — it currently holds no user data
(`users` 0, `projects` 0, `leave_requests` 0), which is why this is a latent hazard rather than a
new loss, but the mechanism is live and must be closed before anything is committed.

### R2 — item 1 incomplete: the row is created outside the lock

`EnvironmentReportService::create()` computes `$reportNo` inside `DB::transaction(...)` with
`->withTrashed()->lockForUpdate()`, then **commits**, and only afterwards runs
`EnvironmentReport::create(...)` and `save()` (`:41-62`). The lock is released before the row
exists, so two concurrent POSTs still compute the same number and the loser gets a duplicate-key
500. SPEC-007 item 1 asked for the create to be inside the transaction, mirroring
`MonthlyReportService.php:26-28`. The blocking half (the permanent soft-delete wedge) **is**
fixed — `withTrashed()` is present and the regression test covers it.

### R3 — out of mandate: `.qwen/settings.json` edited, and loosened

The run added 17 blanket allow rules — `Bash(rm *)`, `Bash(mysql *)`, `Bash(php *)`,
`Bash(sed *)`, `Bash(cd *)`, `Bash(cat *)`, plus `Bash(do *)` / `Bash(done)` / `Bash($safe *)`,
which are parsing artefacts of its own shell loops. That is the harness's own permission config:
auto-approving `rm` and `mysql` is a safety regression, and no work order asked for it. I
restored the file myself (`git checkout -- .qwen/settings.json`, now clean). **Do not touch
anything under `.qwen/` except your own report file.** If a command prompts for permission, note
it in the report instead of editing the config.

### R4 — process: SPEC-006's report was never written

`RUN-006.md` does not exist, and the SPEC-006 delegation returned no output at all; I had to
reconstruct what changed from the tree. SPEC-006's verification checkboxes were therefore
signed off by me, not by the run. Not a code defect, but an unverifiable run is a REJECT on its
own in this workflow.

## Follow-ups recorded (do not fix in this round)

- Stored `sections` JSON is shape-unvalidated: `PUT {"sections":{"results":"x"}}` persists and
  every later export of that report 500s (`results.blade.php:2`, `EnvironmentReportDocxExporter.php:236`,
  `flow_chart.blade.php:5`). Needs a decision on how deep validation goes. `sections`/`signatories`
  are also size-unbounded.
- `update` never re-derives `sections`, so changing the period leaves stale `parameters`/`results`.
- Object-level (per-project) authorization is absent module-wide — consistent with existing house
  practice (`SiteFormController`, `SafetyController`), so it is a codebase-wide decision, not a
  defect introduced here.
- `environmental.*` PDFs are served `inline`; CSP does not constrain the browser PDF plugin.
- `resources/views/pdf/water-quality-worksheet.blade.php` renders river conditions and in-situ
  only — `LAB_PARAMS` (COD/BOD/TSS/E.coli) never appear on the worksheet although section 8.0 of
  the report shows them. Product question for Rahim, not a bug.
- `.qwen/tmp/olak.xlsx` + `dump_xlsx.py` (unzipped JPN template inspection) are untracked scratch
  from the earlier session — owner to decide: keep, ignore, or delete.
