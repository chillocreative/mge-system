# `.qwen/reviews/` — verdicts (owned by the orchestrator, 3.8-Flash)

One file per verdict: `REVIEW-<NNN>-<kebab-slug>.md`, numbered to match its SPEC.

The commit gate reads this directory: `.qwen/hooks/orchestrator-gate.py` refuses `git commit` in the main session unless a `REVIEW-*.md` here contains `Verdict: APPROVE`, **and** that review post-dates the newest `.qwen/runs/RUN-*.md`, **and** post-dates every changed file outside `.qwen/**` and `docs/**` (2-second slack).

Consequences worth knowing:

- Sign the review **last**, after the writer's report has landed — a review cannot certify work that finishes after it.
- Authority comes from the timestamp of an APPROVE, not from file numbering: re-saving an older review re-arms it, and touching application code after a review invalidates the commit until you re-save an APPROVE.
- `README.md` is not matched by the `REVIEW-*` glob, so it never satisfies the gate.
- Emergency bypass: `touch .qwen/hooks/GATE_OFF`, and state the reason in the review file. Remove it after.

Verdict format: [`docs/ORCHESTRATOR.md`](../../docs/ORCHESTRATOR.md). An `APPROVE` with an empty
"Independent verification" section is not an `APPROVE`.
