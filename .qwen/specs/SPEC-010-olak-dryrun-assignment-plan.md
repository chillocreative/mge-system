## SPEC-010 — Report the assignment plan in the dry run

Extracted from REVIEW-008 round 3: a defect that survived review because no assertion covered it.
Found by actually running `php artisan assets:import-olak` against the dev database on 2026-09-22.

### Objective

`php artisan assets:import-olak` (no `--commit`) must tell the operator how many vehicles the OLAK
project would be attached to. Today it always says `Assigns 0` and prints no `ASSIGN` line, so the only
part of the write plan that is invisible is the part that would silently re-point plant across sites.

### Context — why the obvious fix is not enough

`app/Console/Commands/ImportOlakEquipment.php` → `assignProjects(bool $commit, array $touchedRegs)`:

```php
$vehicles = Vehicle::whereIn('registration_no', $touchedRegs)->get();
foreach ($vehicles as $vehicle) {
    $open = $vehicle->projectAssignments()->whereNull('released_at')->first();
    …
    if ($commit) {
        VehicleProjectAssignment::create([ … ]);
        $count++;                      // ← counted only when committing
    }
}
```

Two separate faults, both in this method:

1. `$count++` sits inside `if ($commit)`, so a dry run reports 0 whatever happens.
2. `$vehicles` is read from the **database**. In a dry run the 45 rows that are about to be created do
   not exist yet, so `whereIn` returns almost nothing — on a fresh database it returns zero rows and the
   loop never runs. Moving `$count++` out of the guard fixes the count but still reports `Assigns 0`
   on production, because it is counting existing rows rather than the plan.

Measured evidence from the dev database (`mge_pms`, 2 vehicles, one OLAK project `JPS/IP/BPB/10/2025`):
`Creates 45 | Updates 1 | Docs 33 | Assigns 0 | Retired 0` — the `VBL1055` row exists with no open
assignment and *would* be assigned, yet nothing about assignments was printed.

### Files

- EDIT   `app/Console/Commands/ImportOlakEquipment.php` — `assignProjects()` and the smallest amount of
  plumbing `importAll()`/`handle()` need to feed it. Nothing else in the file.
- EDIT   `tests/Feature/Assets/OlakEquipmentImportTest.php` — add the tests listed under Verification.
- MUST NOT touch: the `MACHINES` / `VEHICLES` constants, any other class, any route, migration, model
  or `resources/js/**` file.

### Contract

`importAll()` already builds a `Vehicle` per row and returns it: `upsertMachine()`/`upsertVehicle()`
return `[$created, $updated, $vehicle]`, where `$vehicle` is a **saved** model under `--commit` and an
unsaved `new Vehicle($attrs)` in a dry run. Change the plan to carry those models instead of plates:

1. Collect `$planned[] = $vehicle` in both modes (the code already receives the model; stop discarding
   it). Keep returning `$touchedRegs` too if anything else uses it — check before removing it.
2. `assignProjects(bool $commit, array $planned)`:
   - Resolve the project exactly as now. `count($projects) !== 1` → keep printing
     `ASSIGN skipped: <n> projects match "OLAK"` and return.
   - Iterate `$planned` — never a `Vehicle::whereIn(...)` query. For each model:
     - `$vehicle->exists` is **false** (dry run, row not yet created): it *would* be assigned →
       `ASSIGN  <registration_no> -> <project code>` and `++$assigns` and `++$wouldCreate`.
     - `$vehicle->exists` is **true**: read its open assignment
       (`released_at = null`), then
       - open assignment to a **different** project → print
         `CONFLICT <reg>: open assignment is project <id>`, `++$conflicts`, assign nothing;
       - open assignment to **this** project → `++$alreadyAssigned`, print nothing per row;
       - no open assignment → `ASSIGN  <reg> -> <project code>`, `++$assigns`, and when `$commit` is
         true also create the `VehicleProjectAssignment` row (attributes exactly as today:
         `vehicle_id`, `project_id`, `assigned_at` = `purchase_date` or today, `released_at` null,
         `created_by` = `$vehicle->created_by`, `notes` = `'Imported from '.self::SOURCE`).
   - The create call stays the **only** write in this method, and it stays behind `$commit`.
3. Summary line: extend the existing line so the split is visible, e.g.
   `Creates 45 | Updates 1 | Docs 33 | Assigns 46 (46 new, 0 existing) | Retired 0`
   — `Assigns` counts rows this run would attach; `already assigned <n>` is fine to fold into the
   parentheses. Keep it one line and keep every existing number meaning what it means today.
4. Do not print 46 separate `ASSIGN` lines if you can help it — but if you suppress them, the count in
   the summary must still be exact. Preferred: print them (the dry run exists to be read). If the
   output length bothers you, print `ASSIGN  <reg> -> <code>` only for the first 10 and then
   `ASSIGN  … (+N more)`; the test below must then match the shape you chose, so pick one and say which
   in the report.

### Constraints

- **MUST NOT** change what `--commit` writes. After this fix, committing must produce exactly the same
  rows as before: 46 vehicles, 33 documents for the dev data set, and one open assignment per imported
  vehicle that is not already attached to OLAK. This SPEC is about *reporting*, not behaviour.
- **MUST NOT** assign a vehicle that is not in `$planned`. That scoping was fixed in SPEC-008 round 1
  (`Vehicle::all()` was assigning every vehicle in the database to OLAK) — do not reintroduce it.
- **MUST NOT** query `Vehicle` by plate inside `assignProjects()` to rebuild the list.
- **MUST NOT** create, update or delete `Project` rows; **MUST NOT** soft-delete or restore anything.
- **MUST NOT** edit the two data constants. I verify them byte-for-byte against SPEC-008 after you finish.
- **MUST NOT** run `assets:import-olak --commit` — it writes to the developer database.
  A read-only dry run (`php artisan assets:import-olak`, no `--commit`) is expected and is your
  acceptance evidence.
- **MUST NOT** run `php artisan migrate` in any form, and **MUST NOT** run `vendor/bin/pint --dirty` —
  `--dirty` rewrites *untracked* files, which includes another live session's work in this tree.
  Scope Pint to the two paths you touched (see Verification).
- **ASK / BLOCKED** instead of guessing if: `$planned` cannot be threaded without changing what
  `--commit` writes; or `$vehicle->exists` proves unreliable for the unsaved instance in this Laravel
  version (report what you observed instead of switching to a DB query).

### Verification

Run these and paste the real output. `timeout` does not exist on macOS. Do not run the full suite —
`php artisan test` fatals at the 128M ini limit here; use
`php -d memory_limit=1G vendor/bin/phpunit` only if I ask for it.

- [ ] `php artisan test tests/Feature/Assets` — every pre-existing test still passes, including
      `test_unrelated_vehicle_is_not_assigned` (46 assignments, `ABC 111` none) and
      `test_legacy_placeholder_rows_are_retired` (the `MGE 1234` guard).
- [ ] `php vendor/bin/pint --test app/Console/Commands/ImportOlakEquipment.php tests/Feature/Assets/OlakEquipmentImportTest.php`
      reports no style issues.
- [ ] `php artisan assets:import-olak` against the dev database now prints an assignment plan. On the
      current dev data (`mge_pms`: 2 vehicles, one OLAK project) the expected tail is
      `Creates 45 | Updates 1 | Docs 33 | Assigns 46 …` with at least one `ASSIGN  … ->  …` line above
      it. Paste that tail verbatim.
- [ ] New tests, all of them, in `tests/Feature/Assets/OlakEquipmentImportTest.php`. Capture output the
      way `test_dry_run_prints_the_full_plan` already does (`withoutMockingConsoleOutput()` +
      `$this->app[Kernel::class]->call(...)`) and count real lines:
  1. `test_dry_run_reports_the_assignment_plan`: empty database, one project whose `name` contains
     `OLAK`, plain dry run → the output contains **46** `ASSIGN` lines (count them) and the summary
     matches `/Assigns\s+46/`, and `VehicleProjectAssignment::count() === 0`.
  2. `test_dry_run_after_commit_reports_already_assigned`: `--commit`, then a plain dry run → summary
     reports `Assigns 0`, mentions 46 already assigned, and `VehicleProjectAssignment::count()` is
     still 46 (the second run must not create a second assignment per vehicle).
  3. `test_conflict_row_is_reported_not_assigned`: `--commit` once; then move ONE imported vehicle's
     open assignment to a second, non-OLAK project; run a dry run → its plate appears in a `CONFLICT`
     line, `Assigns` counts 0 new, and `VehicleProjectAssignment::whereNull('released_at')` did not grow.
  4. Extend or duplicate the multi-match case: two projects matching `OLAK` → `ASSIGN skipped: 2`
     and zero `ASSIGN` lines.
- [ ] No existing assertion is weakened, deleted or renamed. If a test cannot pass without changing
      what `--commit` writes, stop and return BLOCKED — that means this SPEC is wrong, not the test.

### Loop control

- One round is enough for this scope. Hard cap: 2 REJECT rounds, then I take it back myself.
- Turn budget: the writer definition caps at 60. Keep the reading list to the two files named above.
- On block: write the exact question and the verbatim error into `.qwen/runs/RUN-010.md` and return
  BLOCKED. A failed agent status is not evidence the work is missing, and a COMPLETE report is not
  evidence it is done — I re-run everything above.

### Security notes

- Console-only. No route, no request input, no file handling, no new attack surface.
- The defect being fixed is an **availability-of-truth** problem: an operator approving
  `--commit` in a cPanel terminal cannot see one third of what the run will write (46 project
  assignments). Under-reporting a planned write is a review step being defeated, which is how the
  SPEC-008 round 1 dry-run bug reached my desk twice.
- Registration plates, prices and driver names are printed by design for operator review; do not
  `dd()` models, do not log to files, and never read or echo `.env`.
- Mass assignment: no change to `$fillable`; the only new thing is which in-memory models are iterated.
