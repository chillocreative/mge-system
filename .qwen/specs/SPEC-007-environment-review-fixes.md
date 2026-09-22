# SPEC-007 — environment-module-review-fixes + test-runner safety net

## Objective

Fix the two commit-blocking defects the review found in the uncommitted Environment module
(soft-deleted `report_no` wedge; finalised-lock bypass), close three cheap 500/data-loss holes,
and stop `php artisan test` from ever running against the developer's MySQL database again.

No frontend change. **Do NOT run `npm run build`.**

## Context already verified on disk (do not re-litigate; if any of it is false, return BLOCKED)

The Environment module (water quality, per-project environment settings, monthly environment
report, PDF/DOCX exporters, React pages) is uncommitted on `main` at `a298b470`. Its PDF export
was broken by a missing view and was fixed in SPEC-006
(`resources/views/pdf/environment-report/layout.blade.php` now exists,
`cover-page.blade.php` deleted). Measured after that fix:
`env -u DB_CONNECTION -u DB_DATABASE -u DB_HOST -u DB_PORT -u DB_USERNAME -u DB_PASSWORD -u APP_ENV -u LEAVE_ENGINE_ENABLED -u SESSION_DRIVER php -d memory_limit=1G vendor/bin/phpunit`
→ `OK (635 tests, 1807 assertions)`.

**The dangerous fact.** This developer's shell exports `DB_CONNECTION=mysql`,
`DB_DATABASE=mge_pms`, `SESSION_DRIVER` etc. PHPUnit's `<env>` entries in `phpunit.xml`
do **not** override variables already present in the real environment, so `RefreshDatabase`
ran `migrate:fresh` against the **dev MySQL database** and emptied it
(`users` 0, `projects` 0, `leave_requests` 0, `site_logs` 0 — verified 2026-09-22 17:3x).
Proof of the mechanism: `php artisan test tests/Feature/MonthlyReport/RegisterSectionsTest.php`
in the inherited shell failed 7/7 with
`SQLSTATE[23000] … Duplicate entry 'ADM' for key 'correspondence_types…' (Connection: mysql, …, Database: mge_pms)`,
and the same file passed 7/7 once the shell vars were unset. CI is green at HEAD, so this is a
local-environment hazard, not a code defect. **This is why item 0 comes first.**

House patterns to copy (read them before writing):
- `app/Services/MonthlyReport/MonthlyReportService.php:26-28` — `DB::transaction()` +
  `->lockForUpdate()->max('report_no')` for report numbering.
- `app/Services/MonthlyReport/AssetService.php:22,94` + its `guardNotFinal()` — asset writes
  refused on a finalised report.
- `app/Http/Controllers/Api/MonthlyReportAssetController.php:64-67` — asset resolved through
  its parent report, never by bare id.
- `app/Http/Controllers/Api/SiteFormController.php:31,33` — `'project_id' => ['required','integer','exists:projects,id']`.

## Files

- **EDIT** `phpunit.xml` — `force="true"` on the env pins (item 0)
- **EDIT** `tests/TestCase.php` — fail fast if the test connection is not sqlite (item 0)
- **EDIT** `app/Services/Environment/EnvironmentReportService.php` (items 1, 2, 3)
- **EDIT** `app/Http/Controllers/Api/EnvironmentReportController.php` (items 3, 6, 4)
- **EDIT** `app/Http/Controllers/Api/EnvironmentProjectSettingController.php` (items 7, 4)
- **EDIT** `app/Services/Environment/EnvironmentProjectSettingService`? — only if the image
  delete/store ordering lives in a service; if it is inline in the controller (as the review
  quotes it), fix it there. Do not create a new service class.
- **EDIT** `tests/Feature/EnvironmentReportTest.php` — regression tests for items 1, 2, 3
- **EDIT** `tests/Feature/EnvironmentProjectSettingTest.php` — regression tests for items 7, 4
- **EDIT** `tests/Feature/WaterQualityRecordTest.php` — only if item 6's rule also exists there;
  otherwise leave untouched.

## Contract

### 0. The safety net (do this FIRST, before running any test)

`phpunit.xml`: add `force="true"` to exactly these existing pins — `APP_ENV`, `DB_CONNECTION`,
`DB_DATABASE`, `SESSION_DRIVER`, `CACHE_STORE`, `LEAVE_ENGINE_ENABLED`. Leave every other entry
untouched, and leave the existing comment block above `LEAVE_ENGINE_ENABLED` intact (append one
line to it explaining that `force` is there because a developer's shell can export `DB_*` and
that `RefreshDatabase` would otherwise `migrate:fresh` the dev database).

`tests/TestCase.php` `setUp()`: immediately after `parent::setUp()`, abort hard when the default
connection is not sqlite — throw with a message naming the connection, so a misconfigured run
dies in the first test instead of dropping tables. Implement it as one guard, no helpers.

Prove it: `DB_CONNECTION=mysql DB_DATABASE=mge_pms php artisan test tests/Feature/MonthlyReport/RegisterSectionsTest.php`
must now **pass** (7/7) with no MySQL error text anywhere in the output. Before your change that
same command hits MySQL. Paste both the command and the tail of its output.

### 1. `report_no` must count soft-deleted rows, and numbering must be race-safe

`EnvironmentReportService.php:39` computes `max('report_no')` through the default scope, which
`SoftDeletes` (`EnvironmentReport.php:12`) filters by `whereNull('deleted_at')`. Delete the
newest report → `max` returns N-1 → the next create re-issues N → the composite unique index
(`…000010:29`) rejects it with a 500 **forever** for that project (there is no restore path in
the module).

Fix: derive the next number from `withTrashed()` and wrap the create in a transaction with
`lockForUpdate`, mirroring `MonthlyReportService.php:26-28`. Keep the unique index — it is what
makes the race fail closed. Add a regression test: create two reports, delete the second, create
a third → it gets a fresh number and returns 201 (it must fail with 500 before the fix; paste
the before/after).

### 2. Finalised lock must cover assets and deletion

`update`/`regenerate` already abort 422 when `status === 'finalised'`
(`EnvironmentReportService.php:62,94`). `addAsset` (`:138`), `updateAsset` (`:156`),
`removeAsset` (`:169`) and `delete` (`:127`) do not — so a signed, finalised report's evidence
photos/certificates can be swapped or destroyed, and the report itself deleted, while
`finalised_at` and the signature block stay intact.

Fix: one private guard (mirroring `AssetService::guardNotFinal`) applied to all four, so a
finalised report rejects asset writes and deletion with 422. `removeAsset`'s
`Storage::delete` must not run when the guard fires. Add regression tests for all four paths
(expect 422 while finalised, and the same calls succeeding after `reopen`).

### 3. Assets resolve through their report, never by bare id

`EnvironmentReportController.php:163,177,185` do `EnvironmentReportAsset::findOrFail($assetId)`.
Fix per the house pattern: for the two routes that already carry the report (`PUT`/`DELETE`
`/reports/assets/{asset}`) and the download, load the asset **through** its report and reject it
when the owning report is soft-deleted, so writes can't land on a trashed report's evidence and
a deleted report's files stop being addressable. Keep the existing route URIs unchanged — the
frontend (`resources/js/services/environmentReportService.js`) must not need a change; if you
find a shape that forces a frontend edit, return BLOCKED instead.

### 4. `update` must keep the period invariant

`EnvironmentReportController.php:57-58` validate `period_start`/`period_end` as bare `date`;
`store` (`:35`) has `after_or_equal:period_start`. Add the same rule to `update` (and to the
settings controller's period inputs if it has any). Do **not** add section re-derivation — that
is a design decision, not in scope.

### 6. `sort_order` must fit its column

`EnvironmentReportController.php:167` allows any `integer, min:0`; the column is
`unsignedSmallInteger` (max 65535, `…000011:16`) → `{"sort_order":99999999}` is a MySQL
out-of-range 500. Add `max:65535`.

### 7. Settings: validate the project, and never delete before you save

`EnvironmentProjectSettingController.php:22-32,37-56` pass `int $project` straight into
`updateOrCreate`/`firstOrNew` with no `exists:projects,id` → unknown id = FK 500. Add the rule.
`uploadImage` (`:44-56`) deletes the previously stored image (`:46-49`) **before**
`store()`/`save()` — if the save throws, the DB points at a deleted file and the image is lost
from both the UI and every future report, plus an orphan is left on disk. Reorder: store the new
file, persist the new path, delete the old file only after a successful save. Add a regression
test for the unknown-project 422 and, if feasible with the existing test doubles, that a failed
save leaves the previous image intact.

## Out of scope (deliberately — do not "improve" these)

- Validating the *shape* of stored `sections` JSON (the review's finding 5) and unbounded
  payload size; needs a decision about how deep section validation goes.
- Re-deriving `sections` when the period changes.
- Serving PDFs `inline` (finding 8) — intended behaviour for this module; the CSP/nosniff
  headers on `downloadAsset`/`image()` stay exactly as they are.
- River-conditions worksheet omitting lab parameters — a product question, not a defect.
- Any `resources/js/**` change; any migration change; any route URI change.

## Constraints

- MUST NOT: `git add`, `commit`, `push`, `migrate --force`, `db:seed`, or delete files.
- MUST NOT: run `php artisan test` (or any test runner) in a way that leaves `DB_CONNECTION`
  or `DB_DATABASE` inherited from the shell **until item 0 is in place**. After item 0, verify
  once with the explicit `DB_CONNECTION=mysql DB_DATABASE=mge_pms` prefix shown above.
- MUST NOT: change the printed content of the PDF/DOCX report, any Blade view, or
  `EnvironmentReportViewData`.
- MUST NOT: drop or edit any migration, index or FK. `unique(['project_id','report_no'])` stays.
- MUST NOT: weaken the `environmental.*` route middleware in `routes/api.php`.
- MUST: reuse the house implementations named in Context — read them first, do not invent a
  second way to lock a finalised report or number a report.
- PREFER: the smallest guard that closes the path. No new service classes, no traits, no config keys.
- ASK: if a line reference above does not match what you read, or item 3 turns out to need a
  frontend change, STOP and return BLOCKED with the quoted code.

## Verification

- [ ] `grep -n 'force="true"' phpunit.xml | wc -l` → `6`. Quote the `<php>` block.
- [ ] `DB_CONNECTION=mysql DB_DATABASE=mge_pms php artisan test tests/Feature/MonthlyReport/RegisterSectionsTest.php` → 7 passed, and quote any line mentioning `mysql` if one appears (there must be none). This is the anti-data-loss gate.
- [ ] `php artisan test --filter='Environment|WaterQuality'` → paste the summary. Expect 19 passed **plus** every test you added (state the new total).
- [ ] Paste the `⨯`/`✓` lines for your new regression tests, and for items 1 and 2 show the same test failing before the fix (quote your own pre-fix run).
- [ ] `env -u DB_CONNECTION -u DB_DATABASE -u DB_HOST -u DB_PORT -u DB_USERNAME -u DB_PASSWORD -u APP_ENV -u LEAVE_ENGINE_ENABLED -u SESSION_DRIVER php -d memory_limit=1G vendor/bin/phpunit` → paste the final `OK`/`FAILURES` line. Must be green (635 baseline + your new tests).
- [ ] `php vendor/bin/pint --dirty` → quote.
- [ ] `git status --short` → quote verbatim; confirm no `public/build/**` change appeared.
- [ ] `mysql -h 127.0.0.1 -u root -e "SELECT COUNT(*) FROM mge_pms.users;"` → quote it. It must show the same number as before you started (currently `0`) — proof your run did not touch the dev database.

## Loop control

- Retry budget: 3 REJECT rounds. Turn budget: 60.
- On block: write the question into `.qwen/runs/RUN-007.md`, stop, return `BLOCKED: <question>`.

## Security notes

- Items 1-3 are authorization/integrity fixes: the guard must be **server-side** in the service
  layer (the controller is not the boundary), so no client can bypass it.
- Adding `max:65535` / `exists:projects,id` / `after_or_equal` narrows input at the boundary; it
  must not become client-side-only validation anywhere.
- The uploaded-file ordering in item 7 is a data-loss fix: never destroy stored bytes before the
  new state is durable.
- `force="true"` in `phpunit.xml` and the `TestCase` guard are the two mechanisms that keep a
  developer's shell from pointing `RefreshDatabase` at real data. Both must stay.

## Report

Write `.qwen/runs/RUN-007.md` with the verbatim output of every checkbox, and **return a
short final message** summarising: what changed per item, the green full-suite line, and any
item you could not complete. The previous run wrote no report file and returned no output —
the orchestrator cannot sign off a change it cannot see, so the report is a deliverable.
