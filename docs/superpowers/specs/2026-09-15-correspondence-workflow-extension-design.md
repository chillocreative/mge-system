# Correspondence Workflow Extension — Design Spec

Date: 2026-09-15
Status: Approved by user, ready for implementation planning

## Context

The Correspondence module (`ProjectCorrespondence` + friends) already has substantial
workflow infrastructure from an earlier batch ("Ciri 1/16/17/19"):

- `project_parties` — parties on a project (client, consultant, main_contractor, …)
- `correspondence_events` — an append-only history/timeline (raised, handed_over, noted,
  status_changed, closed, reopened), rendered in `CorrespondenceWorkflowDrawer.jsx`
- `current_party_id` on `project_correspondences` — who currently holds it
- `expected_close_date` / `actual_close_date` / `closing_reference` / `closed_by` —
  the existing single close checkpoint
- `CorrespondenceWorkflowService::close()` already enforces: a closing reference AND at
  least one attached file are required before a correspondence can be marked `closed`
- A PDF export (`CorrespondenceController::pdf()` → `pdf.correspondence` Blade view),
  generic across all correspondence types, already showing the type in its header

This spec covers the gaps identified against the user's request — it does **not** rebuild
anything already working.

## Scope

### 1. Data model

New migration on `project_correspondences`:

| Column | Type | Notes |
|---|---|---|
| `other_status_text` | `string`, nullable | Required when `status = 'others'`; null otherwise |
| `client_closed_date` | `date`, nullable | Manually set, no attachment requirement |
| `consultant_closed_date` | `date`, nullable | Manually set, no attachment requirement |

`status` enum extended: `open, pending, closed, declined, forwarded, others` (was:
`open, pending, closed, declined`). MySQL: `ALTER TABLE ... MODIFY status ENUM(...)`
matching the pattern of the existing `add_declined_status_to_project_correspondences_table`
migration (sqlite/test driver falls back to a plain `string` column, same precedent).

`ProjectCorrespondence::$fillable` gains `other_status_text`, `client_closed_date`,
`consultant_closed_date`. `casts()` gains `client_closed_date` and `consultant_closed_date`
as `date:Y-m-d` (matching `expected_close_date`/`actual_close_date`).

### 2. "Others" status behavior

- `other_status_text` is required (`required_if:status,others`) on both store and update.
- When `status` is set to anything other than `others`, `other_status_text` is cleared to
  `null` server-side (in `CorrespondenceService::create()`/`update()`), so stale text never
  lingers and resurfaces if the status is later changed back.
- Everywhere the status is displayed as a badge/label — list table, workflow drawer, PDF —
  when `status === 'others'`, render `other_status_text` as the label instead of the literal
  word "Others". Fall back to "Others" only if `other_status_text` is somehow empty (should
  not happen given the required-if validation, but keep it as a defensive display fallback,
  not a validation loophole).

### 3. `forwarded` status

Just another value in the status enum/dropdown, exactly like `open`/`pending`/`declined` are
today — no automatic linkage to party handover or the two close-date checkpoints below. Users
pick it manually from the same status control that already exists.

### 4. Two closing checkpoints (client / consultant)

- `client_closed_date` and `consultant_closed_date` are plain nullable date fields, editable
  via a small date-picker control added to `CorrespondenceWorkflowDrawer.jsx` (not the main
  list table — the list table is already wide/scrollable, per the drag-scroll work done
  earlier today; these two fields live in the detail drawer and the PDF only).
- Updating them goes through the existing generic `PUT /correspondence/{id}` (`CorrespondenceService::update()`)
  — no new dedicated endpoint, no new `correspondence_events` row, no attachment
  requirement. They do not gate or change `status` in any way.
- Each gets its own field in the drawer's "Status" info grid (alongside the existing
  `Expected close` / `Actual close` display), editable inline by users with `canEdit`.

### 5. Close existing bug: generic update bypasses the reference+attachment guard

Today, `CorrespondenceController@store`/`@update` accept `status: closed` directly through
the plain create/edit form, which writes straight through `CorrespondenceService`, bypassing
`CorrespondenceWorkflowService::close()`'s reference+attachment enforcement entirely. This
contradicts requirement (e) — "every closed correspondence must have reference + attachment"
— since it's currently possible to mark one closed with neither.

Fix: remove `closed` from the validation `in:` list on `store`/`update` (validation becomes
`in:open,pending,declined,forwarded,others` — closed excluded). Closing a correspondence must
go through the dedicated `CorrespondenceWorkflowService::close()` action (already wired to the
"Close…" button in the drawer), which is the only path that can set `status = closed`. If a
request tries to set `status: closed` via store/update, it now fails validation.

Reopening (`status` back to `open`) still goes through `CorrespondenceWorkflowService::reopen()`
as it does today — unaffected by this change since `reopen` was never one of the directly
settable values either.

### 6. PDF report (`resources/views/pdf/correspondence.blade.php`)

- Add two rows to the details grid (same style as existing `Expected close`/`Actual close`
  rows), shown only when non-null: `Client closed` → `client_closed_date`, `Consultant closed`
  → `consultant_closed_date`.
- Add `.badge-forwarded` and `.badge-others` CSS classes alongside the existing
  `.badge-open`/`.badge-pending`/`.badge-closed`/`.badge-declined`.
- Badge text: `{{ $c->status === 'others' ? $c->other_status_text : $c->status }}`.
- No per-type template variation — confirmed the existing generic template (header already
  shows `$c->type`) is sufficient.

### 7. Frontend surfaces to touch

- `resources/js/pages/projects/correspondence/Correspondence.jsx` — status dropdown/select
  in the New/Edit form gains `Forwarded` and `Others` options; selecting `Others` reveals a
  required text input for `other_status_text`. `BADGE_COLORS`/status-label rendering in the
  list table updated for the two new statuses and the others-text-as-label rule. The list
  table's existing status filter dropdown (`All Statuses`) also gains the two new options.
- `resources/js/pages/projects/correspondence/CorrespondenceWorkflowDrawer.jsx` — add
  `Client closed` / `Consultant closed` date pickers next to the existing `Expected close`/
  `Actual close` display; wire to `correspondenceService.update()` (existing method, no new
  service method needed for the date fields themselves — reuses the plain update endpoint).
  Status badge/label logic updated to prefer `other_status_text` when `status === 'others'`.
- `resources/js/services/correspondenceService.js` — no new endpoints required; existing
  `update()` covers the two date fields.

### 8. Tests

Feature tests (PHPUnit) to add/extend, following existing patterns in
`tests/Feature/Correspondence/CorrespondenceWorkflowTest.php`:

- Setting `status: others` without `other_status_text` fails validation.
- Setting `status: others` with `other_status_text` succeeds and persists it.
- Changing status away from `others` clears `other_status_text` to null.
- `status: forwarded` is accepted like any other status value.
- `client_closed_date` / `consultant_closed_date` can be set and read back via update, with
  no attachment/reference requirement.
- Attempting `status: closed` via the plain store/update endpoint now fails validation
  (regression test for the bug fix in section 5).
- The existing `CorrespondenceWorkflowService::close()` path (reference + attachment
  required) is unaffected — still the only way to reach `status = closed`.
- PDF renders without error for a correspondence with `status = others` and both new close
  dates set (smoke test — dompdf view renders, no assertions on binary content beyond a
  200/attachment response).

## Explicitly out of scope

- No per-correspondence-type PDF layouts (confirmed generic template is fine).
- No attachment/reference requirement on the two new close-date checkpoints.
- No new `correspondence_events` timeline entries for the two new close-date checkpoints.
- No automatic status transitions tied to party handover or the two close dates.
- No changes to the existing `project_parties` / handover mechanism.
