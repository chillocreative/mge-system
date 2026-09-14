# REVIEW-005 (SPEC-005) — CLAUDE.md workflow summary

**Reviewed by:** 3.8-Flash (orchestrator) · 2026-09-15
**Artefacts:** `.qwen/specs/SPEC-005-claude-md-workflow-summary.md` · `.qwen/runs/RUN-005.md`
**Implementer:** `writer` (`qwen3.5-flash`) · **Rounds used:** 0 of 3

### Verdict: APPROVE

Pure insertion, verified to the byte. `CLAUDE.md` is the instruction file other coding
agents load, so the text was dictated by me and the writer was restricted to pasting it —
that is why the check below is a byte comparison rather than a style read.

## Blocking

None.

## Independent verification

The decisive test is not "does it read well" but "is anything else in the file altered":

```
$ git diff --numstat -- CLAUDE.md
44      0       CLAUDE.md                       # deletions = 0 → insertion only

# byte-compare the SPEC's "text to insert" block against what landed in the file
SPEC block lines: 44 | inserted span length: 44 | BYTE DIFFERENCES: 0

# remove the inserted range from the working copy and diff the rest against HEAD
baki fail identik dengan HEAD: True

$ git diff --shortstat -- CLAUDE.md
 1 file changed, 44 insertions(+)

$ grep -n "Qwen orchestrator/writer workflow\|# Claude Memory" CLAUDE.md
188:## Qwen orchestrator/writer workflow (since 2026-09-15)
234:# Claude Memory (migrated from Claude to Zed)

$ php vendor/bin/pint --dirty
  PASS   ... 0 files
$ git status --short
 M CLAUDE.md
?? .qwen/specs/SPEC-005-claude-md-workflow-summary.md
```

Placement confirmed by eye too: the section opens after the existing `## Qwen sub-agent`
section's last line, and the pre-existing `---` that introduces the migrated-memory
appendix stayed in place, so the new section sits with the live instructions as intended.

Writer's reported numbers matched every command I re-ran. First clean pass from this model
at the paste-job shape — consistent with the rule that a verbatim block removes its failure
mode; it invented nothing because there was nothing to invent.

## Two SPEC corrections made before delegating (mine, recorded)

- One verification step I first wrote used BSD `sed` syntax (`sed -n '/x/{=;p}'`) and
  **failed on this machine** — macOS `sed` is not GNU `sed`. Proving steps runnable before
  writing them down is the rule SPEC-001 taught; applying it here caught it at the source.
- Another used `grep -c '^-[^-]'`, which prints `0` but **exits 1** when there are no
  matches. Correct-but-red: a writer would faithfully report a failure. Replaced with
  `git diff --shortstat`, and the SPEC now warns not to improvise `grep -c` checks.

## Non-blocking

- The section names `~/.qwen/usage/token-usage-YYYY-MM.jsonl` as an audit path. It is
  machine-local and won't exist for a contributor who has never run this workflow; fine for
  an internal note, worth a caveat if this file is ever shared as onboarding material.
- `CLAUDE.md` grows by 44 lines and is injected into every agent session using it. This
  section earns its place (it prevents four repeated, expensive mistakes), but the file has
  no pruning policy. If it keeps growing, the deploy/build facts belong in a doc that is
  linked rather than loaded.

## Security

- Content review: the inserted text names no credential, customer record, host secret or
  exploitable flaw. It documents the gate's own bypass (`GATE_OFF`) and its enforcement
  rules — information an attacker could use, but the same facts already live in
  `docs/ORCHESTRATOR.md` and in the committed hook, both of which ship to production by
  `git pull` regardless. Adding it to `CLAUDE.md` changes no exposure boundary.
- Because an agent loads `CLAUDE.md` as instructions, this file is a prompt-injection
  surface. The mitigations that applied here: dictated text rather than authored text, a
  0-deletion diff, and a byte comparison against the SPEC. A future edit to `CLAUDE.md`
  should be held to the same three checks.
- No PHP, JS, route, migration or build artifact touched; `npm run build` deliberately not
  run, per SPEC, so `public/build/**` is untouched.

## Tests

Not applicable — Markdown only. `ProjectDocumentCategoryTest` was not re-run for the same
reason; nothing it covers changed.

## Commit scope

`CLAUDE.md` alone, then the workflow records (SPEC-005, this review) as a second commit,
matching the split used for SPEC-003.
