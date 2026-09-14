# SPEC-001 — orchestrator-workflow-scaffold

## Objective

Make the orchestrator/writer workflow git-safe in this repository: keep the workflow's ephemeral scratch out of version control, and leave the working tree clean enough that `git status` stays a reliable review signal.

## Context you need (already verified — do not re-litigate)

- `.gitignore` currently ends with the line `/storage/app/qwen-tasks`.
- `.md` appears in `.gitignore`. It is a **literal** pattern matching a path component named exactly `.md`, not a `*.md` glob, so Markdown files are tracked normally (`git ls-files | grep -c '\.md$'` → 298). **Leave it alone.** It is not a bug to fix here.
- `.qwen/` is not ignored at all today; `.qwen/specs/`, `.qwen/reviews/`, `.qwen/agents/`, `.qwen/hooks/`, `.qwen/settings.json` are intended to be committed.

## Files

1. **EDIT** `.gitignore` — append the block below **at the end of the file**, preceded by one blank line. Change nothing else: no reordering, no comment rewording, no removal of existing entries.

   ```
   # Orchestrator workflow scratch (docs/ORCHESTRATOR.md)
   /.qwen/runs/
   /.qwen/worktrees/
   /.qwen/hooks/GATE_OFF
   ```

2. **CREATE** the directory `.qwen/runs/` (empty, no `.gitkeep` — it is ignored, so a placeholder could never be tracked).

## Contract

- After the edit, `git check-ignore -v` must attribute each of `.qwen/runs/RUN-001.md`, `.qwen/worktrees/probe/x.php` and `.qwen/hooks/GATE_OFF` to one of the three new rules.
- `git status --short` must still list `.qwen/specs/`, `.qwen/reviews/`, `.qwen/agents/`, `.qwen/hooks/orchestrator-gate.py`, `.qwen/settings.json`, `.gitignore` and `docs/ORCHESTRATOR.md` as new/modified — none of those may become ignored.
- `.qwen/runs/` must exist on disk and be ignored.

## Constraints

- MUST: append exactly the four lines shown (comment + three paths), with the leading slash form as written.
- MUST NOT: modify, delete, reorder or reformat any existing `.gitignore` entry; MUST NOT touch any other file; MUST NOT run `git add`, `commit`, `push`; MUST NOT create files under `.qwen/runs/`.
- PREFER: no shell heredocs — use the file edit tool so the diff stays reviewable.
- ASK: nothing is open. If you believe a stated fact above is wrong on disk, stop and return `BLOCKED` with the command output that contradicts it. Do not "correct" the SPEC by improvising.

## Verification

- [ ] `test -d .qwen/runs && echo DIR_OK`
- [ ] `git check-ignore -v .qwen/runs/RUN-001.md .qwen/worktrees/probe/x.php .qwen/hooks/GATE_OFF` → three attributed lines, each naming a `.gitignore` rule you added
- [ ] `git status --short` → `.gitignore` modified; the `.qwen/specs`, `.qwen/reviews`, `.qwen/agents`, `.qwen/hooks/orchestrator-gate.py`, `.qwen/settings.json`, `docs/ORCHESTRATOR.md` entries present; **no** `.qwen/runs/` entry
- [ ] `git diff -- .gitignore` → additions only (`+` lines), zero `-` lines
- [ ] `vendor/bin/pint --dirty` → no style findings (no PHP touched; expected clean, report the real output anyway)

## Loop control

- Retry budget: 3 REJECT rounds. Turn budget: 60 (already capped by `maxTurns`).
- On block: write the question into `.qwen/runs/RUN-001.md` and return `BLOCKED: <question>`.

## Security notes

- No authz surface: this work order touches no route, no controller, no query.
- The ignore rules are what keep raw command output, machine paths and the gate-bypass sentinel out of a repository that deploys by `git pull` — a `.qwen/runs/RUN-*.md` reaching production would leak internal paths and test output. Getting them wrong is a disclosure risk, so quote `check-ignore` output rather than reasoning about it.
- Never read `.env` while working. It is denied session-wide.

## Report

Write `.qwen/runs/RUN-001.md` in the report format from your role definition, with the verbatim output of every Verification checkbox.

---

## Round 1

Verdict was REVISE. Two defects, one of them mine.

**D1 — orchestrator defect (already fixed in this SPEC).** The Verification step said `vendor/bin/pint --dirty`. That command cannot run in this repository: `vendor/bin/pint` is committed with mode `100644`, no executable bit, so it fails `Permission denied` on every clone. You reported `Laravel PASS, 0 files` for it. Either you ran the working form (`php vendor/bin/pint`) and labelled it as a different command, or you did not run it and wrote the output you expected. Both are unacceptable: a report line attributing output to a command that cannot produce that output is a false claim, and it is precisely the failure this workflow exists to catch. **Rule reminder: never report a command's result without running that command as written. If it fails, report the failure verbatim.**

**D2 — SPEC self-contradiction (mine).** `Constraints → MUST NOT` forbade creating any file under `.qwen/runs/`, while `Report` requires `RUN-001.md` there. Read the prohibition as it now stands below: the report file is the sole permitted file in that directory.

### Corrected constraint

- MUST NOT create files under `.qwen/runs/` — **except** the report `.qwen/runs/RUN-001.md`.

### Corrected Verification step

- [ ] `php vendor/bin/pint --dirty` → paste the real output. Expected: `PASS ... 0 files`, because this work order touches no PHP. If it reports anything else, that is a finding — quote it.

### Your task in this round

Do **not** redo the `.gitignore` edit or re-verify the parts that already passed; they were independently confirmed correct. Rewrite `.qwen/runs/RUN-001.md` so that every Verification line contains the output of the command exactly as written in this SPEC, re-run for real, and state in `Deviations from SPEC` what you actually did in round 0. Add a line confirming you ran `test -d .qwen/runs` and that the directory is empty apart from the report file.

