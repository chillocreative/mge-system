# REVIEW-010 — Report the assignment plan in the dry run

SPEC: `.qwen/specs/SPEC-010-olak-dryrun-assignment-plan.md`
Run: `.qwen/runs/RUN-010.md`
Reviewer: Claude (lead), 2026-09-22

## Verdict: APPROVE

Approved on the corrected tree, not on what the writer left behind. The writer's work was uncommitted,
unreported, and failed three of its own tests. The corrections below are part of this commit.

## What the writer got right

The core of the SPEC landed. `importAll()` now collects the planned `Vehicle` models and threads them
into `assignProjects()`, which iterates that array instead of re-querying `Vehicle::whereIn(...)`. The
`$count++` came out from behind `if ($commit)`, `ASSIGN` lines print in both modes, and the
`already assigned` / `CONFLICT` split is counted separately. The one write in the method stayed behind
`$commit`. That is the defect fixed.

## What I corrected

1. **`handleDocuments()` was rewritten to `return 0` on a dry run** — out of scope, and a regression of
   exactly the kind SPEC-010 exists to prevent. It deleted every `DOC` line and zeroed the `Docs 33`
   figure, hiding a second third of the write plan while fixing the first. The SPEC named the two
   methods that could be touched and said every existing number must keep its meaning. Reverted whole.
   This is what broke `test_dry_run_prints_the_full_plan`.

2. **The summary arithmetic could not be right.** It computed `max(0, assigns - already_assigned)` as
   the "new" count, but `assigns` never included `already_assigned` — they are disjoint. On a fresh
   dry run it printed `Assigns 46 (all new)`; with any conflict present the parenthesised split and the
   trailing `already assigned n` restated the same number twice. Replaced with one deterministic form,
   `Assigns <n> (<n> new, <m> already assigned[, <k> conflict(s)])`, whose figures are the counters
   themselves and are never re-derived.

3. **`if ($commit && $vehicle->exists)` guarding the create.** Under `--commit` every planned model is
   saved, so the extra clause is dead — but were it ever false, the run would print `ASSIGN`, count it,
   and write nothing, which is the same class of lie as the bug being fixed. Reduced to `if ($commit)`.

4. **Three tests encoded the wrong expectation.**
   - `preg_grep('/^ASSIGN\s+/')` also matches the `ASSIGN skipped: 2` header, so
     "zero ASSIGN lines" could never pass. Anchored on the arrow instead — and note the obvious
     tightening (`\S+\s+->`) is also wrong, because plates contain spaces (`BPJ 4284`).
   - The conflict test asserted 45 open assignments after moving one to another project. Moving an
     assignment does not close it; the count stays 46. Rewritten to capture the count before the dry
     run and assert it did not change, which is the property actually under test.
   - It also asserted `assertStringContainsString((string) $vehicle_id, $output)` — a low integer that
     appears somewhere in 46 lines of output no matter what. Replaced with the plate on the `CONFLICT`
     line, plus a negative assertion that the same plate never appears on an `ASSIGN` line.

## What I could not verify

`--commit` was not run: the SPEC forbids it and the dev database is already imported. That the commit
path still writes the same rows rests on `test_commit_creates_exactly_46_vehicles`,
`test_idempotent_on_second_commit` and `test_unrelated_vehicle_is_not_assigned`, all passing unchanged.

## Lesson, carried forward from REVIEW-008

REVIEW-008's approval was defeated by an observable no test made anyone count. This round the writer
fixed that observable and broke a neighbouring one in the same file — and its own new tests passed a
green-looking suite only because three of them asserted the wrong thing. A writer report is a claim to
re-run; here there was no report at all, and the tests still had to be read line by line rather than
counted.
