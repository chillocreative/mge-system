# REVIEW-003 (SPEC-003) — project-create documents before notes

**Reviewed by:** 3.8-Flash (orchestrator) · 2026-09-15
**Artefacts:** `.qwen/specs/SPEC-003-project-create-documents-before-notes.md` · `.qwen/runs/RUN-003.md`
**Implementer:** `writer` sub-agent (`qwen3.5-flash`), 28 tool calls, 771k tokens

### Verdict: APPROVE

The change is exactly the specified block move plus three strings. Verified structurally, at byte level against `HEAD`, and in the regenerated bundle. One process finding: **the automated commit gate did not fire** (below), so this approval is manual discipline, not machine enforcement.

## Blocking

None in the code.

## What I verified myself (not the writer's report)

Structure — Documents now precedes Notes and Submit:

```
517: {/* Documents */}      (was 530, {/* Attachments */})
567: {/* Notes */}          (was 517)
580: {/* Submit */}         (unchanged)
```

Fidelity — the two things a "move" usually breaks:

```
# Notes card must be byte-identical to its HEAD version (12 lines):
diff <(sed -n '567,578p' F) <(git show HEAD:F | sed -n '517,528p')   -> IDENTICAL

# moved card must differ from HEAD's Attachments card ONLY by the 3 permitted strings:
diff <(sed -n '517,565p' F) <(git show HEAD:F | sed -n '530,578p' \
  | sed 's|{/\* Attachments \*/}|{/* Documents */}|; s|>Attachments</h2>|>Documents</h2>|; s|Choose files (add multiple)|Add files|')
                                                                        -> IDENTICAL (no drift)

$ grep -c "Attachments" resources/js/pages/projects/ProjectCreate.jsx
0                                  # old label fully gone
$ grep -n 'type="file" multiple' resources/js/pages/projects/ProjectCreate.jsx
524: <input type="file" multiple className="hidden" onChange={addFiles} />
                                   # multi-select intact, handler untouched
$ git diff -U0 -- resources/js/pages/projects/ProjectCreate.jsx | grep '^@@'
@@ -517,14 +517 @@  @@ -532 +519 @@  @@ -536 +523 @@  @@ -579,0 +567,13 @@
                                   # every hunk inside 517..580; no stray edits
```

Tests and build, re-run by me:

```
$ php artisan test --filter=ProjectDocumentCategoryTest
  ✓ a document can be uploaded under each of the three new categories   3.36s
  ✓ the document list can be filtered by the new categories             0.22s
  ✓ an unknown category is still rejected                               0.21s
  Tests:    3 passed (12 assertions)

$ php vendor/bin/pint --dirty
  PASS   ... 0 files                        # no PHP touched, as scoped

$ grep -c "Add files" public/build/assets/app-BQTbRQ0m.js
2                                          # bundle really carries the new copy
$ grep -c "Choose files (add multiple)" public/build/assets/app-BQTbRQ0m.js
0                                          # no stale string survived the build
$ grep -rn "app-D2mMpR_t" public/ resources/ app/ .htaccess
(no output)                                # deleted asset unreferenced
```

`public/build/manifest.json` → `resources/js/app.jsx -> assets/app-BQTbRQ0m.js`; CSS hash unchanged (no style change), which is the expected shape.

## Process finding: the commit gate is dormant

Before writing this review I ran `git commit --dry-run`. `ProjectCreate.jsx` was then newer than every `APPROVE` review on disk, so a live `PreToolUse` hook had to deny it. It was not denied — the command printed its normal dry-run list.

Cause, established rather than assumed: the usage ledger shows this session (`6e16252e`) running continuously from `2026-09-14T17:35:43Z` to `19:14:17Z` under one id. `.qwen/settings.json` was created at ~01:45 local, inside that session, and hooks load only at startup. **The restart was requested but never performed.** This is the exact "do not assume protection exists" case recorded in `.qwen/reviews/README.md`.

Consequence for this commit: the gate's three rules were applied by hand, by me, in this file. The change is safe to commit; what is missing is the machine backstop. Action for the user: `/exit` then relaunch, and re-probe.

## Non-blocking

- Behavioural check of the rendered order is **not** covered by any automated test — this repo has no JS test runner (`package.json` has only `dev`/`build` scripts). Source order and bundle content are proven; the visual result on `/projects/create` still needs one human look.
- `/projects/:id/edit` shares this component, so the reorder lands there too. That is deliberate and was flagged to the user before the work order was issued; if only the create form should change, a follow-up SPEC must branch on `isEdit`.
- The card's sub-copy still reads "Upload multiple files (drawings, contracts, etc.). These become the project's shared files…" — unchanged by design, but now it sits under a heading that says "Documents" instead of "Attachments". Worth a copy pass if the user wants the wording tightened.

## Security

- No authz surface touched: no controller, route, middleware or Form Request changed. `ProjectDocumentController` remains the single validation and permission boundary.
- No client-side restriction was added (`accept`, size, count) — the SPEC forbade it, correctly, since a client rule would imply a security property the server does not gain.
- Upload timing unchanged: files are still pushed after the project exists (lines 211–219), so reordering the card cannot let an unpermitted user reach `uploadDocumentsBulk`.
- Bundled `app-BQTbRQ0m.js` is committed and will ship on the next `git pull` deploy; that is this repo's normal mechanism, and it is why `npm run build` was a required deliverable rather than an optional step.

## Tests

`ProjectDocumentCategoryTest` (3 tests, 12 assertions) is the closest existing regression net — it covers the document upload/ listing/category-validation path this UI feeds. Green before and after. No test asserts DOM order, and none was added: a JSX reorder in a repo with no frontend test harness does not justify standing one up under this SPEC's scope.

## Commit split

1. `fix(projects)` → `2b46afa` — `resources/js/pages/projects/ProjectCreate.jsx`, `public/build/manifest.json`, `public/build/assets/app-BQTbRQ0m.js` (+) and `app-D2mMpR_t.js` (−).
2. `docs(orchestrator)` → `ce8ba9b` — SPEC-003, this review, `.qwen/specs/README.md` numbering note. Code and records kept apart so the production tree only ever receives the first.

Never `git add -A`. No push.

## Addendum — gate confirmed live (same day, after restart)

`/exit` → `qwen --continue`, then two probes as the first actions of the new process:

```
write_file  app/TestGate.php                                 -> DENIED
  Orchestrator gate: the orchestrator may only write under .qwen/** and docs/**.
edit        resources/js/pages/projects/ProjectCreate.jsx     -> DENIED
  (same reason)
```

All three gate rules are now machine-enforced. `2b46afa` remains the single commit that was gated by hand rather than by the hook.

**Correction to my own reasoning, recorded so it is not repeated:** I told the user that the session id changes after a restart and could be used as the objective marker. That is false — `qwen --continue` **resumes under the same session id** (`6e16252e` kept accumulating requests across the restart). Only a fresh `qwen` without `--continue` mints a new id. The reliable probe is the denied write above, never the id.

Incidental: on load, Qwen Code rewrote `.qwen/settings.json` to add `"$version": 4`. Comments, hook matcher and `model.name` came through unchanged — harness bookkeeping, committed apart from application work.
