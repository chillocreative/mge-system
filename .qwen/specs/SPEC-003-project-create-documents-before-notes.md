# SPEC-003 — project-create-documents-before-notes

## Objective

In the New Project / Edit Project form, place the document-upload card **above** the Notes card, and name it "Documents" — so uploading files is not hidden below a notes box.

## Context already verified on disk (do not re-litigate; if any of it is false, return BLOCKED)

`resources/js/pages/projects/ProjectCreate.jsx` (625+ lines; one component serving **both** `/projects/create` and `/projects/:id/edit`):

| Line | Content |
|---|---|
| 515 | `</div>` closing the preceding (Team members) card |
| 517 | `{/* Notes */}` comment |
| 518–528 | Notes card — `<h2>Notes</h2>` + `<textarea name="notes">` |
| 530 | `{/* Attachments */}` comment |
| 531–578 | Attachments card — `<h2>Attachments</h2>`, copy "Upload multiple files (drawings, contracts, etc.)…", label text `Choose files (add multiple)`, `<input type="file" multiple … onChange={addFiles}>`, then the per-file progress list |
| 580 | `{/* Submit */}` block |

Multi-upload **already works** and must not be re-plumbed: `attachFiles` state at line 45, `addFiles`/`removeFile` handlers, and the post-save loop at lines 211–219 calling `projectService.uploadDocumentsBulk(targetId, [attachFiles[i]], …)`. `grep -rn "Choose files (add multiple)\|attachFiles" resources/js --include=*.jsx` matches **only this file** — there are no sibling copies to keep in sync.

## Files

- **EDIT** `resources/js/pages/projects/ProjectCreate.jsx` — move one block, change three strings.
- **REGENERATE** `public/build/**` via `npm run build` (see Verification; these are tracked files).

## Contract

Required DOM order inside `<form>`:

```
{… Team members card …}

{/* Documents */}      <- was {/* Attachments */}, now BEFORE Notes
Documents card        <- internals identical, two strings changed

{/* Notes */}          <- unchanged, byte-identical
Notes card

{/* Submit */}
```

Exactly three textual changes, all inside the moved card:

1. `{/* Attachments */}` → `{/* Documents */}`
2. `<h2 className="mb-1 text-lg font-semibold text-gray-900">Attachments</h2>` → same element, inner text `Documents`
3. `Choose files (add multiple)` → `Add files`

Nothing else changes. The result is a pure reorder plus two labels.

## Constraints

- MUST: keep the Notes card byte-identical (same attributes, same order of props, same placeholder `Internal notes...`).
- MUST: preserve every `className` string in the moved card verbatim, including the dashed-border label and progress-bar classes.
- MUST: keep the file input **inside** the `<form>` element (it is currently, and must stay).
- MUST NOT: touch `attachFiles` state, `addFiles`, `removeFile`, `fileProgress`, `fileErrors`, or the submit/upload loop at 211–219.
- MUST NOT: change any PHP, route, migration, `ProjectDetail.jsx`, `ProjectFilesPanel.jsx`, or any other page.
- MUST NOT: add `accept=`, size caps, count limits, or any client-side validation — the server is the validation boundary, and inventing a client rule here is a scope violation, not an improvement.
- MUST NOT: rename the file, the component, or the "Team members" card above it.
- MUST NOT: `git add`, `commit`, `push`, or delete files under `public/build/` by hand.
- PREFER: a whole-block cut-and-paste over retyping — retyping is how JSX edits pick up silent damage.
- ASK: if the line numbers above no longer match what you read, or the two cards are not adjacent, STOP and return BLOCKED with your `grep -n` output. Do not improvise a different layout.

## Verification

- [ ] `grep -n "{/\* Documents \*/}\|{/* Notes */}" resources/js/pages/projects/ProjectCreate.jsx` → the Documents line number is **lower** than the Notes line number. Paste both numbers.
- [ ] `git diff -- resources/js/pages/projects/ProjectCreate.jsx` → confirm the Notes card shows no changes and the moved card shows only the three string edits. Quote the diff.
- [ ] `npm run build` → completes clean. Vite emits new hashed assets and a new `public/build/manifest.json`. Paste the tail of the output including the emitted file names.
- [ ] `php artisan test --filter=ProjectDocumentCategoryTest` → expect `Tests: 3 passed (12 assertions)`. Orchestrator measured this baseline as PASS before delegating.
- [ ] `php vendor/bin/pint --dirty` → expect `PASS … 0 files` (no PHP touched).
- [ ] `git status --short` → expect: `M resources/js/pages/projects/ProjectCreate.jsx`, `M public/build/manifest.json`, deleted old hashed asset(s), new untracked hashed asset(s). Quote it verbatim.

## Loop control

- Retry budget: 3 REJECT rounds. Turn budget: 60 (`maxTurns` caps it).
- On block: write the question into `.qwen/runs/RUN-003.md`, stop, return `BLOCKED: <question>`.

## Security notes

- No server-side change: `ProjectDocumentController` keeps its authz gate and its file validation. This work order must not weaken or duplicate them in JSX.
- Uploads are attached **after** the project row is created (line 211–219), so a user who cannot create a project still cannot reach the upload path. Reordering the card must not move that call earlier.
- Files land in `storage/app/public/` under project scope; no path is constructed from user input in the client. Do not introduce one.
- `public/build/**` is **committed on purpose**: `deploy.sh` and `.cpanel.yml` contain no `npm`/`vite` step, so production serves whatever hashed bundle is in git. Forgetting `npm run build` here means the reorder ships as nothing — that is why it is a required deliverable, not an optional step.

## Report

Write `.qwen/runs/RUN-003.md` with the verbatim output of every checkbox. No concrete number or PASS claim without having run the command.
