## REVIEW-008 (SPEC-008)

### Verdict: APPROVE (signed at round 3, after rounds 0–2 were REJECTED)

**The round-0 findings below stand as history.** The round-by-round record continues after them and the
approval is at the very end, in `## Round 3 — approval` — read that first if you only want the state of
the code being committed.

Round 1 of 3. The two files exist and the 46 data rows are transcribed correctly (I diffed them — see
Independent verification). Style passes. But the tool is unsafe to run and cannot currently run at all.

### Blocking

- `app/Console/Commands/ImportOlakEquipment.php:90-96` — **the dry run writes to the database.**
  `$commit` is read at `:90` and then used *only* in `summary()` at `:98`. `importAll($creatorId)`
  (`:94`), `retireLegacy()` (`:95`) and `assignProjects()` (`:96`) all receive no commit flag and every
  one of them performs real Eloquent writes (`:196`/`:256` `updateOrCreate`, `:307`
  `VehicleDocument::create`, `:337` mass `->update()`, `:380` `VehicleProjectAssignment::create`).
  There is no rollback anywhere. So an operator who follows the documented workflow — run
  `php artisan assets:import-olak` to *review*, then re-run with `--commit` — mutates production on the
  review pass, and the tool then prints "Dry run — nothing written."
  **Directive:** thread `bool $commit` into `importAll()`, `retireLegacy()` and `assignProjects()`, and
  make each step compute + print its plan but skip every write when `$commit` is false. Keep
  `DB::transaction()` wrapping only the writing path. `test_dry_run_writes_nothing` is the gate for
  this — it must fail before your fix and pass after.
- `app/Console/Commands/ImportOlakEquipment.php:228` — `'serial_no' => $row['serial'] ?: null` inside
  `upsertVehicle()`. `self::VEHICLES` rows have **no `serial` key** (only `MACHINES` does — the
  KENDERAAN sheet has no SERIAL column), so every one of the 20 vehicle rows throws
  `ErrorException: Undefined array key "serial"` and all 12 tests die before asserting anything.
  **Directive:** delete the `serial_no` line from the vehicle attribute array entirely (do not add the
  key to the data rows, and do not use `?? null` — the source carries no serial for these, so the
  command must not write the column at all, exactly like `chassis_no`/`engine_no`).
- `app/Console/Commands/ImportOlakEquipment.php:358` — `$vehicles = Vehicle::all();` assigns **every
  vehicle in the database** to the OLAK project, not just the 46 this command imported. On production
  that is ~177 legacy placeholder machines plus any other site's plant, creating an open
  `vehicle_project_assignments` row for each — cross-project data corruption that is hard to undo.
  The current tests cannot catch it because they run on an empty database where `Vehicle::all()` *is*
  the 46 rows.
  **Directive:** collect the `registration_no` values this command actually upserted and iterate only
  those (`Vehicle::whereIn('registration_no', $touched)->get()`), and add the test described in
  `### Tests` that plants an unrelated vehicle first.
- `app/Console/Commands/ImportOlakEquipment.php:190` and `:250` — the "was" branch prints the literal
  string `was: notes="..."`. The SPEC's Console-output contract requires the *previous* notes truncated
  to 40 chars, and a distinct `(hand-entered row — overwriting)` label for a row whose existing notes
  do not contain `self::SOURCE`.
  **Directive:** emit `was: notes="<substr($noteText,0,40)>"` and add the hand-entered suffix. This
  line is the operator's only warning before `--commit` overwrites something a human typed — a
  placeholder defeats its purpose.
- `tests/Feature/Assets/OlakEquipmentImportTest.php:292` — the "no matching project" test creates
  `Project::create(['name' => 'No OLAK Here', ...])`, and **"No OLAK Here" contains the substring
  "OLAK"**, so the resolver matches one project and the command assigns 46 rows while the test asserts
  0. The fixture contradicts its own assertion.
  **Directive:** rename the project to something with no `OLAK` substring (e.g. `'Bukit Talam'`).

### Non-blocking

- `:91` — prefer the repo's existing idiom `User::role('Admin & HR')->first()?->id`
  (`database/seeders/EquipmentMachineryImportSeeder.php:22`) over the hand-rolled
  `whereHas('roles', …)`. Keep it nullable; do not reintroduce `?? 1`.
- `:293` — `upsertDocument()` takes `$src` and `$site` but never uses them. Drop the dead parameters.
- `:330` — `RETIRE a, b, c, …` joins up to 177 plates onto one line. Print the count plus the first 10
  and `… (+N more)` so the dry-run output stays readable.
- `:94-96` — SPEC order is *retire → import → assign*; here it is import → retire → assign. Harmless
  today (no plate overlap), but the SPEC order is safer if a legacy row ever shares a registration
  with an imported one. Reorder.
- `:248`, `:266`, `:285` — `$actor = User::create([...])` is assigned and never used in three tests.
  Either assert `created_by` equals it or delete the lines.

### Security

- **Blocking (high):** the dry-run defect turns the *safety* step of the documented production
  workflow into the destructive step. This is the whole reason the command is dry-run-by-default; a
  tool that lies about writing nothing is worse than one with no dry run at all.
- **Blocking (high):** `Vehicle::all()` breaks the SPEC's project-scoping rule — writes assignments
  against records belonging to other projects/sites that this import was never about.
- **Clean:** no new HTTP surface (console only, no route added); no `.env` read, no credential in any
  log line; every written key is present in `App\Models\Vehicle::$fillable` and
  `VehicleDocument::$fillable` — no `forceFill`/`unguard`, no `$fillable` widening; legacy retirement
  still matches on the exact notes string, never `LIKE 'MGE-%'` (verified by the `MGE 1234` test, which
  is a good test — keep it).

### Tests

12 tests exist and map to the SPEC's list; they are well-shaped (real assertions, the `MGE 1234`
guard, the duplicate-document guard). Two gaps:

1. Add a case for the `Vehicle::all()` defect: create one unrelated `Vehicle` (e.g.
   `registration_no => 'ABC 111'`, notes not containing `self::SOURCE`, and no OLAK link) **before**
   `--commit`, with exactly one OLAK project, then assert `VehicleProjectAssignment::count() === 46`
   and that `ABC 111` has no assignment. This is the only test that can see the defect at `:358`.
2. After the fixes, `test_idempotent_on_second_commit` should also assert the second run reports
   `Creates 0` — the SPEC's Verification section requires it and the operator relies on that line.

### Independent verification

Everything below is output I ran myself on the working tree as the writer left it. I did not read
`RUN-008.md` before forming these findings (it is dated 18:42; the command file was last modified 18:48,
so the report predates the code — I treated it as a claim, not evidence).

```
$ php artisan test tests/Feature/Assets/OlakEquipmentImportTest.php
  ⨯ dry run writes nothing                          ⨯ commit creates exactly 46 vehicles
  ⨯ spot check machinery row bpj 4284               ⨯ unplated machines get serial as key
  ⨯ motorcycles and cars mapped correctly           ⨯ idempotent on second commit
  ⨯ legacy placeholder rows are retired             ⨯ assigned to is null on all imported rows
  ⨯ existing road tax document not duplicated       ⨯ project assignment with one olak project
  ⨯ project assignment with multiple matching projects
  ⨯ project assignment with no matching project
  Tests:    12 failed (0 assertions)
  … every one of them:  ErrorException  Undefined array key "serial"
      at app/Console/Commands/ImportOlakEquipment.php:228

$ php artisan test tests/Feature/Assets
  Tests:    12 failed, 10 passed (37 assertions)     # the 2 pre-existing files still pass

$ php vendor/bin/pint --test app/Console/Commands/ImportOlakEquipment.php \
      tests/Feature/Assets/OlakEquipmentImportTest.php
  PASS   ....................................... 2 files

# the dry-run claim, read from the file itself:
$ sed -n '90,96p' app/Console/Commands/ImportOlakEquipment.php
        $commit = (bool) $this->option('commit');
        $userWithRole = User::whereHas('roles', fn ($q) => $q->where('name', 'Admin & HR'))->first();
        $creatorId = $userWithRole ? $userWithRole->id : null;
        [$createCount, $updateCount, $docCount] = $this->importAll($creatorId);
        $retiredCount = $this->retireLegacy();
        $assignResult = $this->assignProjects();

# 46 data rows verified against the SPEC mechanically (extract both constant blocks from the PHP
# file and from SPEC-008, whitespace-normalised, compared as ordered lists):
$ python3 <extract-and-diff>
php MACHINES=26 spec MACHINES=26 | php VEHICLES=20 spec VEHICLES=20
MACHINES IDENTICAL (order + every field)
VEHICLES IDENTICAL (order + every field)
total records: 46
reg no fallback set: ['DR 1', 'DR 2', 'FL07', 'FL08', 'JG 1', 'JG 2', 'TBM 1', 'TBM 2']
→ The transcription is correct: 46/46 rows byte-equal to the SPEC, and exactly the 8 unplated
  machines carry a serial (they become DR-1, DR-2, FL07, FL08, JG-1, JG-2, TBM-1, TBM-2).
  Nothing to fix in the data; all Blocking items above are in the logic.
(Note on method: my first run of this comparison was itself buggy — the SPEC slice did not stop at
 `private const VEHICLES`, so it reported "MACHINES 26 vs 46, DIFFERS". Re-cut with the proper
 boundary gave the output above. Recorded here because an unexamined "it matched" would have been
 the same class of error I am rejecting this code for.)

## Round 1 re-review (same verdict: REJECT → Round 2)

What I verified myself after round 1 (not from the writer's report, which claimed "22 tests pass /
Pint clean / idempotent"):

```
$ php artisan test tests/Feature/Assets
  Tests:    22 passed (154 assertions)          # 12 import + 10 pre-existing — matches the claim
$ <constant diff>  MACHINES 26/26 IDENTICAL · VEHICLES 20/20 IDENTICAL   # data untouched, as ordered
$ grep -n "serial_no\|Vehicle::all" …          → one serial_no write (:188, machine path); Vehicle::all gone
$ grep -n "hand-entered\|substr(" …             → :210-211, :274-275 present
$ grep -n "public function test" <test file>    → still 12 methods; no expectsOutput, no ABC 111 fixture
```

So Blocking items 2, 3, 4, 5 from round 0 are genuinely fixed. Item 1 was "fixed" by making the dry run
skip the entire plan, which (a) leaves the operator with no reviewable output and a self-contradicting
`Creates 0 … Assigns 46` summary, and (b) hides a `sole()` lookup that cannot work for rows that do not
exist yet. The required regression tests were not added, which is why both survived a green suite.
Details and directives: `## Round 2` in SPEC-008.

```
$ grep -n 'function importAll' -A 46 ImportOlakEquipment.php | sed -n '/} else {/,/}$/p'
        } else {
            // Dry run: compute what would be imported without writing
            foreach (self::MACHINES as $row) { … $touchedRegs[] = $reg; }
            foreach (self::VEHICLES as $row)  { … $touchedRegs[] = $reg; }
        }
```

Baseline for the full suite, measured before this task started, for comparison at APPROVE time:

```
  php -d memory_limit=1G vendor/bin/phpunit  →  Tests: 645, Assertions: 1845, Failures: 1
  (the 1 failure is the pre-existing ExampleTest 500 — a local sqlite/session-harness artifact,
   production "/" verified 200 OK. It is not this SPEC's defect and not yours.)
```

## Round 3 — approval

### Verdict: APPROVE

The command is correct, the dry run is honest, and every production-safety rule in the SPEC now has a
test standing behind it. Committing `app/Console/Commands/ImportOlakEquipment.php` and
`tests/Feature/Assets/OlakEquipmentImportTest.php` plus this SPEC/REVIEW pair.

All three round-2 Blocking items are resolved:

1. Dry run prints the plan and writes nothing. `importAll()` calls `upsertMachine()`/`upsertVehicle()`/
   `handleDocuments()` in both modes; the only difference is the `if ($commit)` guard around each write.
2. `upsertDocument(Vehicle $vehicle, …)` takes the in-memory model; there is no `sole()` in the file.
3. Both required tests exist and are real (bodies read line by line, not taken from the report).

### Non-blocking, accepted as-is (do not spend another writer round on these)

- `retireLegacy()` still prints all retired plates on one `RETIRE …` line — with ~177 rows that is a
  ~2.5 KB wrapped line in the cPanel terminal. Readable, count included, so accepted.
- `importAll()` duplicates the per-row counting loop across its two branches. Cosmetically redundant;
  the branches exist because only the commit path is wrapped in `DB::transaction`.

### Tests

`test_dry_run_prints_the_full_plan` asserts 46 `CREATE` lines by count, `CREATE  BPJ 4284`, the literal
`DOC     WB160J     road_tax expiry 2027-03-16`, `Creates 46` in the summary, **and**
`Vehicle::count() === 0` — it now proves both halves of the contract. Its pair
`test_dry_run_after_a_commit_reports_updates` commits, re-runs dry, and asserts 46 `UPDATE` lines with
zero `CREATE` lines, so the plan demonstrably reflects database state rather than echoing constants.
`test_unrelated_vehicle_is_not_assigned` guards the scoping fix; `test_legacy_placeholder_rows_are_retired`
guards against the `LIKE 'MGE-%'` mistake with a real Selangor-style plate.

### Security

No new attack surface (console only; no route, no request input, no upload). Writes stay inside
`$fillable`; `assigned_to`, `chassis_no`, `engine_no` are never written; nothing deletes; the destructive
step is behind `--commit` and inside one transaction; no secret or credential is read or logged.

### Independent verification (what I ran, not what the writer reported)

```
$ php artisan test tests/Feature/Assets
  Tests:    25 passed (165 assertions)

$ <constant diff vs SPEC-008>
  MACHINES: IDENTICAL      VEHICLES: IDENTICAL

$ php vendor/bin/pint --test app/Console/Commands/ImportOlakEquipment.php \
      tests/Feature/Assets/OlakEquipmentImportTest.php
  PASS   ....................................... 2 files

$ grep -n 'if ($commit)' ImportOlakEquipment.php            → 7 guards
  vs 2 updateOrCreate + 2 ::create( + 1 ->update(           → every write site is covered
$ grep -c 'sole()'                                          → 0
$ stat -f '%Sm %N' …
  19:05:29 app/Console/Commands/ImportOlakEquipment.php   ← last written in round 2
  19:08:04 .qwen/specs/SPEC-008-olak-machinery-import.md   ← my round-3 directive
  19:10:51 tests/Feature/Assets/OlakEquipmentImportTest.php ← round 3 touched ONLY the test
  → the round-3 writer kept to its boundary even though it died on MAX_TURNS afterwards.

$ php -d memory_limit=1G vendor/bin/phpunit
  Tests: 660, Assertions: 1973, Failures: 1, PHPUnit Deprecations: 13.
  1) Tests\Feature\ExampleTest::test_the_application_returns_a_successful_response
     Expected response status code [200] but received 500.
[2026-09-22 11:15:39] local.ERROR: no such table: sessions (sqlite :memory:)
```

The single failure is the same pre-existing one measured before this work began (645 → 660 tests, still
exactly 1 failure, `ExampleTest` both times). It is the root SPA route hitting the `sessions` table with
no migrated database, it does not touch vehicles, and it is outside this SPEC. Correction to something I
wrote earlier in this file: the `SESSION_DRIVER force="true"` change that landed in `6072f70f` does **not**
cure it — the failure persists, so it remains an open harness item for a separate round, and production
is unaffected (`curl -L https://app.mge-eng.com` → `200`, `<title>MGE-PMS>`).

### What I could not verify, and why

I could not run the command end to end against a throwaway SQLite database: the project's own deny rule
refuses `php artisan migrate --force`, and I did not seek a workaround for a denied action. The dry-run
and `--commit` behaviour is therefore certified by the feature tests above (which execute the real command
through the framework kernel and capture its real output), not by a production-shaped run. The operator
must still read the dry-run output before passing `--commit`.

### Post-approval correction (same day, after `01add380` was pushed)

This APPROVE was incomplete, and the reason is worth keeping on record. Running the real dry run against
the developer database showed `Creates 45 | Updates 1 | Docs 33 | Assigns 0 | Retired 0` — the dry run
never reports the assignment plan at all, because `assignProjects()` counts inside `if ($commit)` **and**
rebuilds its list by querying `Vehicle` for rows that a dry run has not created. So one third of what
`--commit` writes is invisible to the person approving it.

My round-3 approval asserted the `CREATE`/`UPDATE` line counts and never asserted an `ASSIGN` line, so a
green suite certified a plan that was partly blank. Fixed under `SPEC-010`
(`.qwen/specs/SPEC-010-olak-dryrun-assignment-plan.md`). The lesson stands as written: an APPROVE is only
as good as the observable it actually made someone count, and running the tool for real is a review step,
not a release step.


