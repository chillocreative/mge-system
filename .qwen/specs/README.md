# `.qwen/specs/` — work orders (owned by the orchestrator, 3.8-Flash)

One file per work order: `SPEC-<NNN>-<kebab-slug>.md`.

- `NNN` is monotonic and never reused. Take it from the highest number already on disk **across both `specs/` and `reviews/`**, so a work order and its verdict always share one number:
  `ls .qwen/specs .qwen/reviews | grep -o '[0-9]\{3\}' | sort -n | tail -1`
- Gaps are legitimate. `SPEC-002` was never issued: the v1.2 writer-model switch touched only orchestrator-owned files (`.qwen/**`, `docs/**`) and needed no work order, but it consumed `REVIEW-002`. Do not backfill a number — the sequence is shared with `reviews/`.
- The writer never edits a SPEC. Rejected rounds are appended **to the same file** as `## Round <k>`.
- Committed, and therefore deployment-visible (this repo ships by `git pull`): no credentials, no customer data, no exploitable vulnerability detail.


Template and rules: [`docs/ORCHESTRATOR.md`](../../docs/ORCHESTRATOR.md).
