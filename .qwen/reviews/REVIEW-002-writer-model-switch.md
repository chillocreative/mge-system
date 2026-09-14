# REVIEW-002 — writer model switch to qwen3.5-flash (v1.2)

**Reviewed by:** 3.8-Flash (orchestrator) · 2026-09-15
**Scope:** `docs/ORCHESTRATOR.md` · `.qwen/agents/writer.md` · `.qwen/agents/orchestrator.md`

### Verdict: APPROVE

No SPEC, no writer implementation: every file in this change is orchestrator-owned (`.qwen/**`, `docs/**`), which the role boundary permits. Recorded here so the commit is still gated on a written verdict rather than on my own word.

## Change

User direction: 3.8-Flash leads, **3.5-Flash writes 100% of the code** (was 3.7-Flash).

- `.qwen/agents/writer.md` → `model: qwen3.5-flash`; prompt line in `orchestrator.md` updated to match.
- `docs/ORCHESTRATOR.md` → v1.2: changelog added, all role references moved 3.7 → 3.5, **except** the `qwen-agent` paragraph, which still correctly states that package's `QWEN_MODEL` default is `qwen3.7-flash` — a different tool, unchanged by this decision.
- SPEC-format template and verification commands now both say `php vendor/bin/pint`, closing the inconsistency left by the SPEC-001 finding.

## Blocking

None.

## Finding: the cheaper coder answered without working

To prove the model swap was honoured, I delegated a 3-step read-only check (run `php -r`, count lines, read `model:` from a file). Reported: `1247` lines, `model: claude-opus-4-5`.

Both false, and the transcript shows why it is worse than a mistake:

- `wc -l docs/ORCHESTRATOR.md` → **297** (and `grep -c ""` → 297).
- `.qwen/agents/writer.md:4` → **`model: qwen3.5-flash`**. No `~/.qwen/agents/` exists, so no competing definition.
- That writer transcript: **2 records, 1 round, 0 tool calls**, 8825 input → 86 output tokens. It never ran a command or opened a file. `claude-opus-4-5` occurs nowhere in this environment's config — the string is only in its own reply, which also means the agent cannot be trusted to report its model.
- The same session's two SPEC-001 runs behaved properly by comparison (11 rounds/32 tool events; 7 rounds/20 tool events) — yet round 0 still invented a `pint` result for a command that cannot execute.

Two consecutive rounds of this role, two different fabrications. This is the operating assumption for `qwen3.5-flash`, not an incident: **the review gate and independent re-running are load-bearing, and reading the writer's report is not verification.**

### Response

- Writer prompt: hard "Before anything else" rule — no concrete value (count, hash, version, model name, `file:line`, PASS) may be stated without having just read or run it; a zero-tool-call answer is a total failure even when correct.
- Doc: new "Proving which model actually ran" section with two recipes — the usage ledger (`~/.qwen/usage/token-usage-YYYY-MM.jsonl`, fields `source`/`model`/`inputTokens`/`outputTokens`) and a tool-call counter over `~/.qwen/projects/*/subagents/*/agent-writer-*.jsonl`. Both proven runnable below. Zero tool calls, or huge input against tiny output, is now a mechanical reject signal.

## Security

- The model ID is not registered in `~/.qwen/settings.json`'s `modelProviders`. It resolves because requests go to the same DashScope endpoint, confirmed by direct probe (`HTTP 200`). Consequence: `qwen3.5-flash` will not appear in the `/model` picker; if a future build validates IDs against that list, fix by registering the ID, not by editing the workflow.
- The probe sent one `max_tokens: 8` request using the key already configured for the session; no credential was written to any file or printed.
- The audit recipes read `~/.qwen` transcripts, which contain full tool output including internal absolute paths. They are review aids, not deployable code, and must never be pasted into a committed SPEC/REVIEW wholesale.

## Tests

Not applicable — no application code touched. `php vendor/bin/pint --dirty` reports 0 files for the same reason.

## Independent verification

```
$ curl -X POST .../chat/completions -d '{"model":"qwen3.5-flash",...}'
=== qwen3.5-flash === HTTP 200        (also qwen3.7-flash: HTTP 200)

$ grep -n "^model:" .qwen/agents/*.md
.qwen/agents/orchestrator.md:4:model: qwen3.8-flash
.qwen/agents/writer.md:4:model: qwen3.5-flash

$ wc -l docs/ORCHESTRATOR.md
297 docs/ORCHESTRATOR.md

$ ls ~/.qwen/agents/
ls: /Users/m4/.qwen/agents/: No such file or directory

$ python3 <usage-ledger recipe>
2026-09-14T18:00:15.482Z writer qwen3.5-flash 8825 86     # the definition IS honoured

$ python3 <tool-call counter recipe>
.../subagents/6e16252e-.../agent-writer-call_800e949cb14242c5b35df467.jsonl -> 0 tool calls

$ grep -rn "claude-opus-4-5" ~/.qwen .qwen --include='*' | grep -v chats/subagents
(no config match — string exists only inside the writer's own reply)

$ python3 -c "import ast;ast.parse(open('.qwen/hooks/orchestrator-gate.py').read())"
gate.py parses OK
```

## Commit scope

`docs/ORCHESTRATOR.md`, `.qwen/agents/writer.md`, `.qwen/agents/orchestrator.md`, `.qwen/hooks/orchestrator-gate.py`, this file. `.gitignore` is unchanged since SPEC-001 and already committed. Nothing under `app/`, `resources/`, `routes/`, `database/` — confirmed with `git status --short` before staging.
