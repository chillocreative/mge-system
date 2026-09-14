# `.qwen/specs/` — work orders (owned by the orchestrator, 3.8-Flash)

One file per work order: `SPEC-<NNN>-<kebab-slug>.md`.

- `NNN` is monotonic and never reused. Take it from the highest number already on disk:
  `ls .qwen/specs | sed -n 's/^SPEC-\([0-9]*\).*/\1/p' | sort -n | tail -1`
- The writer never edits a SPEC. Rejected rounds are appended **to the same file** as `## Round <k>`.
- Committed, and therefore deployment-visible (this repo ships by `git pull`): no credentials, no customer data, no exploitable vulnerability detail.

Template and rules: [`docs/ORCHESTRATOR.md`](../../docs/ORCHESTRATOR.md).
