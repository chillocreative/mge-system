# SPEC-008 — round-2 fixes for REVIEW-006 (test-runner safety net + numbering lock)

## Objective

Close the two rejected items in `.qwen/reviews/REVIEW-006-…`: make it **impossible** for a test
run to reach the developer's MySQL database (R1), and put the report row create **inside** the
numbering lock (R2). Nothing else changes.

## Context already verified on disk (do not re-litigate; if any of it is false, return BLOCKED)

Round 1 (SPEC-007 item 0) tried two mechanisms and **both failed**, measured by the orchestrator:

1. `phpunit.xml` `force="true"` on `DB_CONNECTION`/`DB_DATABASE` does not win against a real
   exported environment variable:
   ```
   $ DB_CONNECTION=mysql DB_DATABASE=mge_pms php artisan test tests/Feature/MonthlyReport/RegisterSectionsTest.php
     Tests:    7 failed (0 assertions)     <- RuntimeException: Tests must use sqlite, not mysql.
   ```
   It failed with 7 tests, so `config('database.default')` was still `mysql`.
2. The `tests/TestCase.php` guard fires **after** `parent::setUp()`, and `RefreshDatabase` runs
   `migrate:fresh` *inside* `parent::setUp()` — so the guard reports the wipe instead of
   preventing it. Proof, taken immediately after that run:
   ```
   $ mysql -h 127.0.0.1 -u root -e "SELECT TABLE_NAME, CREATE_TIME FROM information_schema.TABLES
       WHERE TABLE_SCHEMA='mge_pms' AND TABLE_NAME IN ('users','correspondence_types');"
     correspondence_types  2026-09-22 18:12:22
     users                 2026-09-22 18:12:21
   ```
   Those tables were recreated by the 18:12 test run.

Current `tests/TestCase.php` (18 lines) is:
```php
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite') {
            throw new \RuntimeException('Tests must use sqlite, not '.config('database.default').'.');
        }
    }
}
```
Current `phpunit.xml` already carries `force="true"` on `APP_ENV`, `CACHE_STORE`, `DB_CONNECTION`,
`DB_DATABASE`, `SESSION_DRIVER`, `LEAVE_ENGINE_ENABLED` — **leave it exactly as it is**; it still
protects `vendor/bin/phpunit` runs and documents intent.

Current `EnvironmentReportService::create()` (`app/Services/Environment/EnvironmentReportService.php:39-65`):
`$reportNo = DB::transaction(fn … withTrashed()->lockForUpdate()->max(…))` — the transaction
**commits**, and only then `EnvironmentReport::create([...])`, `$report->sections = …`,
`$report->save()` run outside it. House pattern to copy:
`app/Services/MonthlyReport/MonthlyReportService.php:26-28` (read it first).

## Files

- **EDIT** `tests/TestCase.php` — force the env before boot (item A)
- **EDIT** `app/Services/Environment/EnvironmentReportService.php` — create inside the lock (item B)
- **EDIT** `tests/Feature/EnvironmentReportTest.php` — only if item B needs an assertion added

Do not touch any other file. **`.qwen/**` is off limits except your own report** — see Constraints.

## Contract

### A. Force the test database in PHP, before the application boots

In `tests/TestCase.php::setUp()`, as the **first** statement (before `parent::setUp()`), set
`DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` through all three channels Laravel can read —
`putenv()`, `$_ENV`, `$_SERVER` — in a small `foreach`, then keep the existing post-boot
`config('database.default') !== 'sqlite'` throw as an assertion.

Why all three: Laravel's `env()` reads the Dotenv repository, which prefers `$_ENV`/`$_SERVER`
and falls back to `getenv()`; setting only one leaves the other two able to win.

Add one comment line stating the reason (a developer's shell may export `DB_*`, and
`RefreshDatabase` would otherwise `migrate:fresh` real data) — no other comments.

**The guard must be proven to prevent the wipe, not to report it.** Verification item A-3 below
is the whole point of this work order.

### B. Number and create the report inside one locked transaction

`create()` must, inside a single `DB::transaction()`: compute `max('report_no')` with
`withTrashed()->lockForUpdate()`, create the row, set `sections`/`signatories`/`generated_at`,
`save()`, and return the report. The reload (`->load([...])`) may stay outside. Keep the
`'status' => 'draft'`, `created_by`/`updated_by` assignments and the title default
`"MONTHLY ENVIRONMENT REPORT NO.{n}"` byte-identical. No behaviour change for any caller.

## Constraints

- MUST NOT: edit `phpunit.xml`, `bootstrap/app.php`, `config/**`, any migration, any route, or
  any file under `resources/`.
- MUST NOT: write to, migrate, seed, or `migrate:fresh` `mge_pms`. The only MySQL command
  allowed in this work order is the read-only `information_schema` canary in Verification.
- MUST NOT: modify anything under `.qwen/` other than `.qwen/runs/RUN-008.md`. In particular do
  **not** edit `.qwen/settings.json` — round 1 added 17 auto-approve rules (`Bash(rm *)`,
  `Bash(mysql *)`, …) and the orchestrator reverted them. If a command needs permission, run it
  and report what happened; never widen the harness config yourself.
- MUST NOT: `git add`, `commit`, `push`, or `npm run build`.
- MUST NOT: refactor `guardNotFinal()`, the asset scoping, or the settings controller from round
  1 — they are accepted as-is.
- MUST: keep the suite green for the *other* reason too — run the full suite with the `env -u`
  form in Verification, since the local `php -d memory_limit` default (128M) is too small.
- ASK: if `tests/TestCase.php` or `EnvironmentReportService::create()` differs from the code
  quoted above, STOP and return BLOCKED with the current contents.

## Verification

Run A-1/A-2/A-3 **in that order** and paste each verbatim.

- [ ] A-1 canary baseline (read-only):
      `mysql -h 127.0.0.1 -u root -e "SELECT TABLE_NAME, CREATE_TIME FROM information_schema.TABLES WHERE TABLE_SCHEMA='mge_pms' AND TABLE_NAME IN ('users','projects','correspondence_types','migrations') ORDER BY TABLE_NAME;"`
- [ ] A-2 the hostile-environment gate:
      `DB_CONNECTION=mysql DB_DATABASE=mge_pms php artisan test tests/Feature/MonthlyReport/RegisterSectionsTest.php`
      → expect **`Tests: 7 passed`** and quote any line containing `mysql` (there must be none).
      This is the exact command that produced 7 failures before your change.
- [ ] A-3 canary after: repeat A-1. Every `CREATE_TIME` must be **identical to A-1** — that is the
      proof no `migrate:fresh` touched the dev database. Paste both and state it in one sentence.
- [ ] A-4 sanity that the forcing did not hide a real connection problem:
      `php -r 'var_dump(getenv("DB_CONNECTION"));'` and the normal safe run
      `env -u DB_CONNECTION -u DB_DATABASE -u DB_HOST -u DB_PORT -u DB_USERNAME -u DB_PASSWORD -u APP_ENV -u LEAVE_ENGINE_ENABLED -u SESSION_DRIVER php artisan test --filter='Environment|WaterQuality'` → quote the summary (expect 29 passed as in round 1).
- [ ] B-1 `php artisan test tests/Feature/EnvironmentReportTest.php` → quote the ✓/⨯ list; the
      numbering regression tests from round 1 must still pass.
- [ ] B-2 paste the new `create()` method in full so the reviewer can see the transaction
      boundary with their own eyes.
- [ ] Full suite:
      `env -u DB_CONNECTION -u DB_DATABASE -u DB_HOST -u DB_PORT -u DB_USERNAME -u DB_PASSWORD -u APP_ENV -u LEAVE_ENGINE_ENABLED -u SESSION_DRIVER php -d memory_limit=1G vendor/bin/phpunit`
      → paste the final line. Round 1's baseline is `OK (645 tests, 1845 assertions)`; it must be
      equal or better, and green.
- [ ] `php vendor/bin/pint --dirty` → quote.
- [ ] `git status --short` → quote verbatim; confirm `public/build/**` unchanged and that no
      `.qwen/**` file appears except your report.

## Loop control

- Retry budget: this is round 2 of the 3 rounds allowed on REVIEW-006. Turn budget: 40.
- On block: write the question into `.qwen/runs/RUN-008.md`, stop, return `BLOCKED: <question>`.

## Security notes

- Item A is a data-integrity control, not a convenience: until it is proven by A-3, every
  developer who exports `DB_*` in their shell can destroy their development database with a
  single `php artisan test`. Do not weaken it, and do not move the forcing after `parent::setUp()`.
- Item B closes the last race in report numbering; the composite unique index stays as the
  fail-closed backstop.

## Report

Write `.qwen/runs/RUN-008.md` with the verbatim output of every checkbox **and return a final
message** summarising A and B with the A-1/A-3 CREATE_TIME comparison. A run that returns
nothing cannot be signed off (see REVIEW-006 R4).
