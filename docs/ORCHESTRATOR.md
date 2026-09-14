# Qwen Orchestrator — Multi-Model Architecture

> **Project:** MGE-PMS — Construction Project Management System
> **Stack:** Laravel 12 (PHP 8.2+) · React 19 · Vite 7 · Tailwind CSS 4 · MySQL · Sanctum · Spatie Permission
> **Concept:** Qwen 3.8-Flash as brain, Qwen 3.5-Flash as hands.
> **Version:** 1.2 (2026-09-15) — rewritten against the real Qwen Code sub-agent contract.
> **Supersedes:** v1.0 (2026-09-14), which described an agent config format that Qwen Code does not implement.

---

## Changelog

- **v1.2** — writer moved to `qwen3.5-flash` at the user's request: 3.8-Flash leads, one cheaper model writes 100% of the code. `qwen3.5-flash` is **not** in the local `modelProviders` list; it resolves because the agent's request goes to the same DashScope endpoint (verified 2026-09-15: `HTTP 200`). If the picker ever refuses it, register the ID in `~/.qwen/settings.json` rather than changing the workflow.
- **v1.1** — mechanics corrected against the real sub-agent contract (below).

---

## What changed in v1.1

v1.0 was sound as a division of labour but wrong on mechanics. Corrected here:

| v1.0 claimed | Reality |
|---|---|
| `agents: { orchestrator: {...} }` block inside `settings.json`, with `restrictions: ["cannot_edit_files"]` | There is no `restrictions` key. Sub-agents are **Markdown files with YAML frontmatter** in `.qwen/agents/`. Access control is `tools` / `disallowedTools` — not a bespoke field. |
| Orchestrator "has no edit_file / write_file permission in agent config" | The orchestrator is the **main session**, which always owns the full tool set. A `tools` allowlist only applies to sub-agents. As written, the rule was unenforceable. |
| `resources/js/Pages/Jprn/Members.vue` | This project is **React**, not Vue, and has no Inertia. Pages live in `resources/js/pages/` (lowercase `.jsx`). |
| Writer "asks before guessing" | Sub-agents get **no interactive question tool**. A writer cannot prompt the user mid-run; it must return a `BLOCKED` report instead. |
| Approval mode implies a sandbox | A permissive parent overrides a restrictive child: with the parent in `yolo`, a sub-agent declared `approvalMode: plan` still runs `yolo`. |
| No bound on the REJECT loop | Unbounded 3.8→3.5→3.8 loops are the obvious failure mode. Capped in [Loop control](#loop-control). |
| Nothing about two writers sharing one working tree | Concurrent agents on the same checkout overwrite each other. Now addressed in [Concurrency](#concurrency). |

---

## Roles

### Qwen 3.8-Flash — **Lead Orchestrator** (main session)

Architect, auditor, planner, reviewer, gatekeeper. Does not author application code.

**Deliverables:** task SPECs · architectural decisions · code reviews of writer output · bug reports with `file:line` + repro · security findings with severity + fix directive · test plans · the commit itself.

**Write access (enforced):** `.qwen/**` and `docs/**` only. Everything else in the tree requires a work order.

### Qwen 3.5-Flash — **Code Writer** (sub-agent)

Executor. Authors 100% of application code, fixes, migrations, factories, seeders, tests. Never deviates from the SPEC, never invents patterns, never refactors outside scope.

**Write access (enforced):** the whole tree, minus the tool restrictions in its definition.

---

## The Workflow

```
User request
    │
    ▼
3.8 Orchestrator ── explores (read_file / grep / git log) ── writes .qwen/specs/SPEC-NNN-<slug>.md
    │ SPEC path + one-line objective
    ▼
3.5 Writer (background sub-agent) ── implements ── runs verification ── writes .qwen/runs/RUN-NNN.md
    │ completion notification (result arrives in a later turn)
    ▼
3.8 Orchestrator ── git diff, line by line ── re-runs tests independently ── writes .qwen/reviews/REVIEW-NNN-<slug>.md
    │
    ├── REJECT ──► appends directives to the same SPEC ──► send_message to the same task_id (max 3 rounds)
    └── APPROVE ─► git add <paths> + git commit ─► report to user
```

A background sub-agent's result is **not** visible inline. It is delivered as a completion notification in a subsequent turn. Do not fabricate or predict it, and do not relaunch a duplicate while one is running — use `list_agents` then `send_message` with its `task_id`.

---

## Agent definitions (real format)

Files live in `.qwen/agents/` (project, highest precedence) or `~/.qwen/agents/` (user). Managed with `/agents create` and `/agents manage`. Body = the system prompt. Supported frontmatter: `name`, `description`, `model`, `approvalMode`, `tools`, `disallowedTools`, `maxTurns` (plus Claude-Code compat fields `permissionMode`, `color`, `mcpServers`, `hooks`).

This project ships `.qwen/agents/writer.md` and `.qwen/agents/orchestrator.md`. Model IDs are resolved against the provider already configured in `~/.qwen/settings.json` (`qwen3.8-flash`, `qwen3.5-flash`).

To let the model pick a tier without hard-coding IDs, `agents.modelGrades` + `agents.allowedGrades` are available in `settings.json`.

### Proving which model actually ran

Do not ask the agent what model it is — it will guess. The usage ledger records every request with its serving model and the agent that issued it:

```bash
python3 - <<'PY'
import json, os, collections
f = os.path.expanduser('~/.qwen/usage/token-usage-%s.jsonl' % __import__('time').strftime('%Y-%m'))
rows = [json.loads(l) for l in open(f, errors='replace') if l.strip()]
for r in rows[-20:]:
    print(r['timestamp'], r.get('source'), r.get('model'), r.get('inputTokens'), r.get('outputTokens'))
PY
```

`source` is the agent name (`main`, `writer`, `Explore`). Two readings matter for review:

- **`source: writer` + the expected `model`** ⇒ the definition is being honored. A model ID that is absent from `modelProviders` still resolves if the endpoint accepts it (that is how `qwen3.5-flash` works here).
- **A writer request with a high `inputTokens` and tiny `outputTokens`, or a transcript with zero `functionCall` parts** ⇒ the agent answered without working. That is a fabricated report; reject the run regardless of whether the answer looks right.

```bash
# tool events in the newest writer transcript (0 = it never touched anything)
python3 - <<'PY'
import json, glob, os
f = max(glob.glob(os.path.expanduser('~/.qwen/projects/*/subagents/*/agent-writer-*.jsonl')), key=os.path.getmtime)
n = sum(1 for l in open(f, errors='replace') for p in (json.loads(l).get('message') or {}).get('parts') or []
        if isinstance(p, dict) and 'functionCall' in p)
print(f, '->', n, 'tool calls')
PY
```

---

## Enforcement: what is hard, what is a promise

| Rule | Mechanism | Enforced? |
|---|---|---|
| Writer cannot spawn sub-agents | `tools` allowlist omits `agent` | **Hard** |
| Writer cannot prompt the user | sub-agents have no question tool | **Hard** |
| Writer turn budget | `maxTurns: 60` | **Hard** |
| Writer must not read `.env` | `permissions.deny: ["Read(.env)"]` — file rules are also enforced against equivalent shell (`cat`, `grep`, `cp`, …) | **Hard**, session-wide |
| Orchestrator must not edit app code | prompt rule + commit gate below | **Soft** — see below |
| Nothing reaches git history without a written APPROVE | `PreToolUse` hook `.qwen/hooks/orchestrator-gate.py` | **Hard** |

### The commit gate (the load-bearing rule)

Vetoing the orchestrator's keystrokes is not achievable from inside the main session — it has a shell, and a shell can write any file. So the architecture does not try. It enforces the **merge**, not the typing:

`orchestrator-gate.py` denies `git commit` in the main session unless all three hold:

1. some `.qwen/reviews/REVIEW-*.md` contains `Verdict: APPROVE`;
2. that review is newer than the newest `.qwen/runs/RUN-*.md` — a review cannot certify work that finished after it;
3. nothing in the worktree outside `.qwen/**` and `docs/**` is newer than that review — no silent post-sign-off edits, and no unreviewed new file.

Authority is the *timestamp of the APPROVE*, never "the highest-numbered file": re-saving an old review or holding two reviews at once must not move the goalposts. Comparison allows a 2-second slack for write races. Sub-agents bypass the gate entirely (detected via `agent_type` in hook input); `touch .qwen/hooks/GATE_OFF` disables it for an emergency, with the reason recorded in the review.

Unreviewed code therefore cannot enter history, cannot deploy, and cannot be lost silently.

Remaining, accepted gap: an orchestrator that writes code and *never commits it* is undetectable. `git status --short` before every hand-off is how the human checks the promise.

---

## SPEC format (3.8 → 3.5)

```markdown
## SPEC-<NNN> — <slug>

### Objective
<one sentence — the outcome, not the implementation>

### Files
- CREATE `app/Http/Controllers/Api/FooController.php`
- EDIT   `routes/api.php`
- DELETE `<obsolete file>`

### Contract
<inputs → outputs → side effects; name the route, the permission gate, the DB writes>

### Constraints
- MUST:     <hard requirement>
- MUST NOT: <prohibition — including "no opportunistic refactoring">
- PREFER:   <existing pattern/service/repository to reuse, with path>
- ASK:      <decision the writer cannot make alone → it must return BLOCKED, not guess>

### Verification
- [ ] `php artisan test --filter=<X>` passes
- [ ] `php vendor/bin/pint --dirty` clean
- [ ] `npm run build` clean (only if `resources/js/**` touched)
- [ ] <behavioural check, stated as an observable result>

### Loop control
- Retry budget: 3 REJECT rounds.
- Turn budget: 60 (`maxTurns` also caps it).
- On block: write the question into `runs/RUN-<NNN>.md`, stop, report BLOCKED.

### Security notes
<authz gate, validation boundary, data exposure, mass assignment, file upload path>
```

`3.5` never edits the SPEC. When a directive is genuinely ambiguous, `3.8` amends the SPEC and re-issues — an ambiguous SPEC is an orchestrator defect, not a writer error.

---

## REVIEW format (3.8 → user / 3.5)

```markdown
## REVIEW-<NNN> (SPEC-<NNN>)

### Verdict: APPROVE | REJECT | REVISE

### Blocking
- `<file>:<line>` — <defect> — <directive: change X to Y because Z>

### Non-blocking
- `<file>:<line>` — <improvement>

### Security
- <findings, severity, and whether they block>

### Tests
- <coverage gaps; assertions to add>

### Independent verification
<verbatim output of the commands 3.8 actually ran — not the writer's claims>
```

A verdict of APPROVE with an empty "Independent verification" section is not an APPROVE. `3.8` re-runs `php artisan test` and reads `git diff` itself; the writer's report is a claim to test, not evidence.

---

## Rules of engagement

### Orchestrator (3.8)
1. Never authors application code — not one line. It is a work order.
2. Explore before planning: read the files, `git log`, existing services and tests.
3. A SPEC must be executable without guessing.
4. Every SPEC analyses authz, input validation and data exposure.
5. Verify with tools, not vibes.
6. Reject with a reason **and** a fix directive.
7. One reviewer per change; the writer never self-approves.
8. Escalate to the user when the *requirements* are wrong, not the code.
9. May write only under `.qwen/**` and `docs/**`.
10. Never push. Pushing is the user's action.

### Writer (3.5)
1. Never invent architecture. Undecided in the SPEC ⇒ return `BLOCKED` with the exact question.
2. Never refactor outside the SPEC — unrequested fixes are defects.
3. Follow project rules (`CLAUDE.md`, existing conventions in the file's neighbourhood).
4. Never claim success without running the SPEC's verification steps.
5. Report honestly: "test fails", "pint reports 3 issues".
6. Never read or echo `.env`, secrets, credentials; never touch production.
7. Preserve changes you did not make — they belong to the user.
8. Write every artefact of the run into `.qwen/runs/RUN-<NNN>.md`, including failing output.

---

## Handoff protocol

1. `3.8` writes `.qwen/specs/SPEC-<NNN>-<slug>.md`; `NNN` is monotonic — take it from the highest number already on disk, never reuse.
2. `3.8` launches the writer: `Agent(subagent_type="writer", prompt="Read /abs/path/.qwen/specs/SPEC-007-x.md and implement EXACTLY. Write your report to /abs/path/.qwen/runs/RUN-007.md. Do not commit. Do not edit the SPEC.")`, background.
3. `3.5` implements, verifies, writes `runs/RUN-<NNN>.md`, lists changed files, stops. **No commit.**
4. `3.8` reviews, writes `reviews/REVIEW-<NNN>-<slug>.md`.
5. REJECT → append `## Round <k>` directives to the SPEC, then `send_message(task_id, "Re-read SPEC-<NNN>, section Round <k>; apply the directives and update RUN-<NNN>.md")`. Reusing the agent keeps its context; relaunching discards it.
6. APPROVE → `git add` the SPEC's paths only (never `-A`), commit, report the commit SHA.
7. Cap: 3 REJECT rounds ⇒ stop, report to the user with the outstanding directives.

---

## Concurrency

One writer per working tree. The tree is shared state; two agents editing `routes/api.php` will lose one of the edits with no conflict marker.

For genuinely parallel work, isolate: `Agent(..., isolation="worktree")` gives a throwaway copy under `.qwen/worktrees/` (path and branch returned if it changed anything), and `working_dir="<existing linked worktree>"` pins a named agent to a worktree you created. Merge back yourself; the commit gate runs per checkout and does not see across worktrees.

---

## Directory layout

```
.qwen/
├── agents/       # writer.md, orchestrator.md      — committed
├── specs/        # SPEC-NNN-<slug>.md   (3.8 owns) — committed
├── reviews/      # REVIEW-NNN-<slug>.md (3.8 owns) — committed
├── runs/         # RUN-NNN.md  (3.5 owns)          — gitignored, ephemeral
├── hooks/        # orchestrator-gate.py           — committed
│   └── GATE_OFF  # sentinel, bypasses the gate    — gitignored
└── settings.json # model, permissions, hook wiring — committed (holds no secrets)
docs/
└── ORCHESTRATOR.md
```

Committed: `agents/`, `specs/`, `reviews/`, `hooks/orchestrator-gate.py`, `settings.json`. Gitignored: `runs/` (raw command output and machine paths), `worktrees/`, `hooks/GATE_OFF`.

⚠️ **Deploy consequence:** this repo ships by `git pull` on the production server (`deploy.sh`, `.cpanel.yml`), so everything above lands on `app.mge-eng.com`. Never paste credentials, customer data, or exploitable vulnerability detail into a SPEC or REVIEW — they are deployment-visible by construction.

---

## Verification commands

```bash
php artisan config:clear && php artisan test      # full suite (composer test does config:clear + test)
php artisan test --filter=ProjectsTest            # one class
php vendor/bin/pint --dirty                       # style, changed files only
npm run build                                     # vite production build
php artisan migrate                               # local only — production runs this via deploy.sh
```

> ⚠️ **`php vendor/bin/pint`, never `vendor/bin/pint`.** `vendor/bin/*` is committed with mode `100644` — no executable bit — so running the shim directly fails with `Permission denied` on every fresh clone, including production. Found in the SPEC-001 pilot (2026-09-15); a verification step must be proven runnable before it goes into a SPEC. The same applies to `vendor/bin/phpunit`.

Frontend: `resources/js/pages/*.jsx`, services in `resources/js/services/*Service.js`, auth state via `AuthContext`, gates via `usePermission()` / `<PermissionGate>`. Backend: Controller → Service → Repository → Model, permission gates with Spatie in controllers, form requests in `app/Http/Requests/`.

---

## Relationship to the existing `qwen-agent` package

`chillocreative/qwen-agent` (in `require-dev`, path repo at `../qwen-agent`) implements the same idea one layer down: `php artisan qwen:agent task.txt --context=<files> --out=<out>.md` runs a **read-only** CLI (its `QWEN_MODEL` default is `qwen3.7-flash`, set in `.env`), writing nothing, while the lead applies the patch. Scratch space is `storage/app/qwen-tasks/` (gitignored).

Keep both: this document is the default flow; `qwen:agent` remains useful for a one-shot diff proposal with no file access, and for non-Qwen-Code contexts.

> ⚠️ **Do not remove the `repositories` block from `composer.json`** — the package is not on Packagist; the entry is what lets Composer resolve it from our own repo, which is also a supply-chain consideration reviewed on 7 Sep 2026. Keep it in `require-dev`, never `require`: deploy runs `composer install --no-dev`.

---

## Failure modes

| Failure | Mitigation |
|---|---|
| Writer invents patterns | SPEC's `MUST NOT` + `ASK`; review rejects non-conforming code |
| SPEC too vague | Writer returns `BLOCKED` instead of guessing; 3.8 amends the SPEC |
| Writer claims tests pass when they don't | 3.8 re-runs independently; the REVIEW must quote its own output |
| Code changes after sign-off | Gate rule 3: worktree newer than the review ⇒ commit denied |
| Review written before the work finished | Gate rule 2: review older than `runs/` ⇒ commit denied |
| Orchestrator writes code anyway | Soft; `git status --short` before hand-off reveals it. Only `.qwen/**`+`docs/**` are hard-blocked |
| Infinite REJECT loop | 3-round cap, then escalate |
| Two writers collide | one writer per tree, or `isolation="worktree"` |
| Destructive shell by writer | `permissions.deny` for `rm -rf`, migrations `--force`, `git push`; review diffs before commit |
| Secret pasted into a committed SPEC | gitignore discipline above + human check; rotate anything that slips |

---

## Summary

- **3.8 thinks, reviews, commits. 3.5 types.**
- Every change needs a written SPEC *and* a written REVIEW before it can exist in git history — the second is machine-enforced, the first is the plan of record.
- Do not trust a report; run the command.

**File authored:** 2026-09-14 · **v1.2 revised:** 2026-09-15 · MGE-PMS · Orchestrator concept v1.2
