# SPEC-005 — claude-md-workflow-summary

## Objective

Record in `CLAUDE.md` what the Qwen orchestrator/writer workflow is and the project facts it discovered, so a future session does not have to rediscover them.

## Files

- **EDIT** `CLAUDE.md` — insert **one new section**, verbatim from the block below, at exactly one place.

Nothing else in the repository changes.

## Contract

Insert the fenced block under "The text to insert" (without the outer ```` ```markdown ```` / ```` ``` ```` fence lines) immediately **before** the line

```
# Claude Memory (migrated from Claude to Zed)
```

and after the existing `## Qwen sub-agent (Claude-as-lead delegation)` section (currently
line 168; `# Claude Memory …` is currently line 190 — treat those as hints, match on the
heading text, not the number). Keep one blank line before and after. The file must otherwise
be byte-identical to what it is now.

Placement is load-bearing: `# Claude Memory …` opens a migrated-memory appendix; the new section belongs with the live project instructions above it.

## The text to insert

```markdown
## Qwen orchestrator/writer workflow (since 2026-09-15)

This project runs a two-model split inside Qwen Code, documented in
[`docs/ORCHESTRATOR.md`](docs/ORCHESTRATOR.md). It supersedes the "Claude applies the
patch by hand" pattern above for work done in Qwen Code; `php artisan qwen:agent` still
exists for one-shot read-only diff proposals.

- **Qwen 3.8-Flash = lead.** Plans, writes SPECs, reviews, commits. Never authors
  application code — not even a one-line fix.
- **Qwen 3.5-Flash = writer.** Authors 100% of the application code from a written work
  order, runs the verification steps, reports into `.qwen/runs/`.
- Paper trail: `.qwen/specs/SPEC-NNN-<slug>.md` → `.qwen/runs/RUN-NNN.md` →
  `.qwen/reviews/REVIEW-NNN-<slug>.md` → commit. Hard cap of 3 REJECT rounds, then escalate.

Enforced by a `PreToolUse` hook, `.qwen/hooks/orchestrator-gate.py`:

- The lead may write only `.qwen/**` and `docs/**` in this repo (plus its own agent and
  memory files under `~/.qwen/`). Anything else — including this file — is refused.
- `git commit` is refused unless some `REVIEW-*.md` contains `Verdict: APPROVE` **and** that
  review post-dates the writer's run report **and** every changed file outside those two
  areas. Signing off cannot be skipped, and code edited after a review cannot ride along
  on an older approval.
- Emergency bypass only: `touch .qwen/hooks/GATE_OFF`, and record why in the review.

Four project facts this workflow already paid to discover:

1. `vendor/bin/*` is committed **without the exec bit**, so `vendor/bin/pint` dies with
   `Permission denied` on every clone, local and production. Use `php vendor/bin/pint …`
   (and the same for `phpunit`).
2. **`public/build/**` is tracked and production never runs vite** — `deploy.sh` and
   `.cpanel.yml` contain no npm step, so the server serves whatever hashed bundle is in git.
   Any `resources/js/**` change is incomplete until `npm run build` runs and the new hashed
   asset plus `public/build/manifest.json` are committed. A "still showing the old UI" report
   is one of: not deployed, not built, or the wrong page — in that order of likelihood.
3. `.gitignore` contains a line `.md`, which matches a path component named exactly `.md`;
   it is **not** `*.md`, and Markdown is tracked normally. Leave it alone.
4. The writer model confabulates. Observed: reporting a `PASS` for a command that cannot
   execute, and answering a three-step read-only check with zero tool calls and two invented
   facts. Treat every writer report as a claim to re-run, not evidence. Per-request
   `source`/`model`/token counts are auditable in `~/.qwen/usage/token-usage-YYYY-MM.jsonl`.

Deploy is unchanged: `git pull` then `bash deploy.sh` in the cPanel terminal (that is where
`artisan migrate --force` runs).

```

## Constraints

- MUST: insert the block character-for-character as given. Do not reword, reflow, re-order the numbered facts, or "improve" the Markdown.
- MUST NOT: modify, delete, reorder or re-wrap any existing line of `CLAUDE.md`, including the existing `## Qwen sub-agent (Claude-as-lead delegation)` section and the `# Claude Memory (migrated …)` heading.
- MUST NOT: touch any other file; MUST NOT run `npm run build` (no `resources/js/**` change — regenerating would churn `public/build/` for nothing).
- MUST NOT: run `git add`, `commit`, `push`.
- MUST NOT: invent, add or "complete" any claim in the block from your own reasoning. If a statement looks wrong to you, stop and return BLOCKED with the evidence — do not edit it.
- PREFER: a single edit operation over several, so the diff stays reviewable as one insertion.
- ASK: nothing is open.

## Verification

- [ ] `git diff --numstat -- CLAUDE.md` → deletions column must be **0** (pure insertion). Paste the line.
- [ ] `grep -n "Qwen orchestrator/writer workflow\|# Claude Memory" CLAUDE.md` → the new heading's line number is **lower** than the `# Claude Memory` line. Paste both.
- [ ] `git diff --shortstat -- CLAUDE.md` → must report insertions only, with **no** "deletion(s)" in the output. Paste verbatim.
- [ ] `wc -l CLAUDE.md` → report the real number, and `git show HEAD:CLAUDE.md | wc -l` for the baseline (orchestrator measured baseline **225**).
- [ ] `php vendor/bin/pint --dirty` → expect `PASS … 0 files` (no PHP touched).
- [ ] `git status --short` → only ` M CLAUDE.md`. Paste verbatim.

Note on exit codes, so a passing check is not misreported as failure: these commands are chosen to exit `0`. Do not add `grep -c` yourself to count removed lines — `grep -c` exits `1` when the count is zero, which looks like a failure but is the desired result; use `--shortstat`/`--numstat` above instead.

## Loop control

- Retry budget: 3 REJECT rounds. Turn budget: 60 (`maxTurns` caps it).
- On block: write the question into `.qwen/runs/RUN-005.md` and return `BLOCKED: <question>`.

## Security notes

- `CLAUDE.md` is an instructions file loaded by coding agents: an insertion here is a privilege change, which is why the text is dictated rather than authored by you. Deviating from the block is not a style issue, it is content injection into another agent's context.
- The block documents that `.qwen/**` and `docs/**` are deployment-visible because this repo ships by `git pull`. Do not add real secrets, credentials, tokens, customer data or exploitable vulnerability detail to it.
- Do not read `.env` while working. It is denied session-wide.

## Report

Write `.qwen/runs/RUN-005.md` with the verbatim output of every checkbox, in the report format from your role definition.
