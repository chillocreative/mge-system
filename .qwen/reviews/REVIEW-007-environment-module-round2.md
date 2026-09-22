# REVIEW-007 — round 2 sign-off (SPEC-006, SPEC-007, SPEC-008 / Environment module)

**Reviewer:** orchestrator · **Date:** 2026-09-22 18:55 (+08) · **Supersedes:** REVIEW-006 R1, R2
**Baseline:** `a298b470` + the uncommitted Environment module

### Verdict: APPROVE

Both blockers from REVIEW-006 are closed, and I verified them myself rather than trusting
`RUN-008.md`. Scope of this sign-off: the Environment module (water quality, per-project
environment settings, monthly environment report + PDF/DOCX exporters + React pages), its tests,
the PDF layout fix, the seven review fixes, and the test-runner safety net.

## R1 (database safety net) — CLOSED, measured by the orchestrator

`tests/TestCase.php` now forces `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` through
`putenv()`, `$_ENV` and `$_SERVER` as the first statement of `setUp()`, before the application
boots, and keeps the post-boot assertion. The hostile command that produced `7 failed` in round 1
now passes, and the read-only canary proves the dev database was not physically touched:

```
$ DB_CONNECTION=mysql DB_DATABASE=mge_pms php artisan test tests/Feature/MonthlyReport/RegisterSectionsTest.php
  Tests:    7 passed (29 assertions)

$ mysql ... SELECT TABLE_NAME, CREATE_TIME FROM information_schema.TABLES
    WHERE TABLE_SCHEMA='mge_pms' AND TABLE_NAME IN ('users','projects','correspondence_types','migrations')
  before:  correspondence_types 18:12:22 | migrations 18:12:18 | projects 18:12:21 | users 18:12:21
  after:   correspondence_types 18:12:22 | migrations 18:12:18 | projects 18:12:21 | users 18:12:21   <- identical
```

I went further than the SPEC and ran the **whole suite** under a deliberately hostile environment
(`DB_CONNECTION=mysql DB_DATABASE=mge_pms DB_HOST=127.0.0.1 DB_USERNAME=root SESSION_DRIVER=file`),
which is the everyday failure mode:

```
  OK (645 tests, 1845 assertions)     Time: 02:34.193, Memory: 199.00 MB
```

Environment/WaterQuality subset under the same hostile env: `OK (29 tests, 112 assertions)`.

## R2 (numbering lock) — CLOSED

`EnvironmentReportService::create()` is now a single `DB::transaction()` that computes
`max('report_no')` with `->withTrashed()->lockForUpdate()` **and** creates the row, sets
`sections`/`signatories`/`generated_at`, saves and returns the reloaded model inside the same
transaction, matching `MonthlyReportService.php:26-28`. The soft-delete wedge (the blocking half)
and the lock-release-before-insert race are both gone; the composite unique index remains the
fail-closed backstop.

## Also accepted (unchanged since round 1, re-confirmed)

SPEC-006 layout fix; `guardNotFinal()` on `update`/`regenerate`/`delete`/`addAsset`/`updateAsset`/
`removeAsset`/reorder; assets resolved through `whereHas('report', … withoutTrashed())`;
`after_or_equal:period_start` on `update`; `sort_order` `max:65535`; settings controller validating
the project before touching storage, with store → save → delete-old ordering.
`.qwen/settings.json` was clean at the start of this round (my R3 revert held through the writer's
run — the `Bash(eval *)` entry that appeared at 18:46 belongs to the concurrent OLAK session, not
to this work order, and is left alone).

## Not certified by this review

- **Concurrent work in the same tree.** A second live session is mid-flight on an unrelated
  "OLAK machinery import": `app/Console/Commands/ImportOlakEquipment.php` and
  `tests/Feature/Assets/OlakEquipmentImportTest.php` (untracked, 18:44-18:48),
  `.qwen/specs/SPEC-008-olak-machinery-import.md` (name-collides with this round's SPEC-008), and
  regenerated `vendor/composer/autoload_{classmap,static}.php`. None of it is covered by this
  sign-off and none of it may enter a commit made under it.
- **Incidental damage from my own verification command.** `vendor/bin/pint --dirty` treats
  untracked files as dirty, so it reformatted those two OLAK files
  (`single_quote, control_structure_braces, unary_operator_spaces, no_unused_imports`) while I was
  checking this module's formatting. Style-only, not recoverable via git (the files are untracked).
  My command, not the writer's; the lesson is that `pint --dirty` is unsafe to run while another
  session has uncommitted work — scope it to paths instead.
- **The development database contents.** `mge_pms` is empty (`users` 0, `projects` 0,
  `leave_requests` 0) and its tables carry `CREATE_TIME` of today. `RefreshDatabase` +
  `migrate:fresh` against the shell-exported MySQL connection is the mechanism, which is now
  blocked, but the emptied state is not a code question and needs the owner's decision
  (`mge_prod_snapshot`, imported 7 Sep, is intact at 94 tables).
- Follow-ups listed in REVIEW-006 (section-JSON shape validation, period-change re-derivation,
  per-project authorization codebase-wide, inline PDFs, worksheet lab parameters).

## Commit condition

`git add` only the Environment-module paths, `phpunit.xml`, `tests/TestCase.php` and this module's
tests — explicitly, never `-A`/`.` — while the OLAK session's files and `vendor/**` stay unstaged.
If the concurrent session writes anything new after this review, the gate will (correctly) refuse
the commit and a fresh review is required.
