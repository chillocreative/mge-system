# Correspondence Workflow Extension Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Project override:** This repository requires 100% of code authorship to go through
> `qwen-agent` (see `CLAUDE.md` → "Qwen sub-agent" / memory `qwen-100-percent-coding-delegation`).
> Whichever execution mode is chosen, every code-writing step below must be routed through
> `php artisan qwen:agent <task>.txt --context=<files>`, with the lead reviewing qwen's proposed
> diff before applying it via Edit/Write — never write source changes directly. Test runs, Pint,
> and `npm run build` are still run directly (qwen is read-only and cannot execute anything).

**Goal:** Extend the Correspondence module with two new statuses (`forwarded`, `others` with
free-text), two independent client/consultant close-date checkpoints, updated PDF output, and a
fix for the existing bug that lets the generic update endpoint set `status = closed` without the
required reference + attachment.

**Architecture:** Purely additive on top of the existing Ciri-7 workflow infrastructure
(`project_parties`, `correspondence_events`, `CorrespondenceWorkflowService`) — no new tables, no
new workflow-service methods. One migration adds three nullable columns and widens the `status`
enum; controller validation and `CorrespondenceService` gain the `other_status_text`
clear-on-change rule and the closed-transition guard; the Blade PDF view and two React
components get new fields wired to the existing generic update endpoint.

**Tech Stack:** Laravel 12 (PHP 8.2+), MySQL (enum via raw `ALTER TABLE`, sqlite fallback to
`string` for tests), Pest-style PHPUnit feature tests, React 19, barryvdh/laravel-dompdf.

**Spec:** `docs/superpowers/specs/2026-09-15-correspondence-workflow-extension-design.md`

## Global Constraints

- MySQL enum changes use raw `DB::statement("ALTER TABLE ... MODIFY status ENUM(...)")`; the
  sqlite test driver falls back to a plain `string` column — follow the exact pattern already
  used in `database/migrations/2026_08_06_000003_add_declined_status_to_project_correspondences_table.php`.
- `other_status_text` is required only when `status === 'others'` (`required_if:status,others`)
  and is cleared to `null` server-side whenever `status` changes to anything else.
- `client_closed_date` / `consultant_closed_date` are plain nullable dates: no attachment
  requirement, no new `correspondence_events` row, do not affect `status`.
- The generic `store`/`update` endpoints must never be able to set `status = closed` for a
  correspondence that is not already closed — only `CorrespondenceWorkflowService::close()` can
  do that (unaffected by this plan). An update that leaves an already-closed correspondence's
  status at `closed` (a no-op) must still be allowed, so editing other fields on a closed record
  doesn't force a spurious reopen.
- No per-type PDF layouts. No changes to `project_parties` or the handover mechanism.
- All new/changed PHP files must pass `php vendor/bin/pint --test` and the full
  `php artisan test` suite before a task is considered done.

---

### Task 1: Migration + model support for the new columns

**Files:**
- Create: `database/migrations/2026_09_15_000004_add_status_extension_to_project_correspondences.php`
- Modify: `app/Models/ProjectCorrespondence.php`
- Test: `tests/Feature/Correspondence/CorrespondenceWorkflowTest.php`

**Interfaces:**
- Produces: `ProjectCorrespondence` fillable/cast attributes `other_status_text` (string|null),
  `client_closed_date` (Carbon|null, `Y-m-d`), `consultant_closed_date` (Carbon|null, `Y-m-d`).
  `status` column now accepts `open|pending|closed|declined|forwarded|others` at the DB layer
  (application-level enforcement of which values are *settable* through which endpoint is Task 2).

- [ ] **Step 1: Write the qwen task prompt for the migration + model change**

Create `storage/app/qwen-tasks/correspondence-status-extension-migration.txt`:

```
TASK: Add three nullable columns to project_correspondences and widen its `status` enum.

Create a new migration file at exactly this path:
database/migrations/2026_09_15_000004_add_status_extension_to_project_correspondences.php

It must follow the exact pattern of the existing migration
database/migrations/2026_08_06_000003_add_declined_status_to_project_correspondences_table.php
(shown in context) for the enum change: MySQL gets a raw
`ALTER TABLE project_correspondences MODIFY status ENUM('open','pending','closed','declined','forwarded','others') NOT NULL DEFAULT 'open'`,
sqlite/other drivers fall back to `$table->string('status')->default('open')->change();`.

In the SAME migration's up(), also add these three nullable columns via Schema::table (a plain
Blueprint change, not raw SQL, added AFTER the enum change, in a separate Schema::table call):
- `other_status_text` — `$table->string('other_status_text')->nullable()->after('status');`
- `client_closed_date` — `$table->date('client_closed_date')->nullable()->after('expected_close_date');`
- `consultant_closed_date` — `$table->date('consultant_closed_date')->nullable()->after('client_closed_date');`

down() must: drop the three new columns via Schema::table, then revert the enum/string column
back to the previous state (mirror the down() logic in the reference migration — for mysql,
`ALTER TABLE project_correspondences MODIFY status ENUM('open','pending','closed','declined') NOT NULL DEFAULT 'open'`;
for other drivers, `$table->string('status')->default('open')->change();`). Before narrowing the
enum back down, first downgrade any rows using the removed values so the ALTER doesn't fail:
`DB::table('project_correspondences')->whereIn('status', ['forwarded', 'others'])->update(['status' => 'open']);`.

ALSO update app/Models/ProjectCorrespondence.php (shown in context):
- Add 'other_status_text', 'client_closed_date', 'consultant_closed_date' to the $fillable array.
- Add 'client_closed_date' => 'date:Y-m-d' and 'consultant_closed_date' => 'date:Y-m-d' to the
  casts() array, following the same style as the existing 'expected_close_date'/'actual_close_date'
  entries.

OUTPUT: the full new migration file content, and a diff for the two small changes to
ProjectCorrespondence.php.
```

- [ ] **Step 2: Run qwen and review the output**

```bash
php artisan qwen:agent storage/app/qwen-tasks/correspondence-status-extension-migration.txt \
  --context=database/migrations/2026_08_06_000003_add_declined_status_to_project_correspondences_table.php,app/Models/ProjectCorrespondence.php \
  --out=storage/app/qwen-tasks/correspondence-status-extension-migration-out.md
```

Read the output file. Verify: migration filename matches exactly, up()/down() both handle mysql
and non-mysql branches, column order (`after(...)`) matches the spec, and the model diff only
touches `$fillable` and `casts()` — nothing else in the model changes.

- [ ] **Step 3: Apply the reviewed migration and model changes**

Use Write to create the migration file with qwen's reviewed content; use Edit to apply the two
model changes exactly as reviewed (fix anything qwen got wrong before applying — e.g. if it used
the wrong `after()` column or missed a driver branch).

- [ ] **Step 4: Run the migration and confirm the schema**

```bash
php artisan migrate --pretend
php artisan migrate
```

Expected: migration `2026_09_15_000004_add_status_extension_to_project_correspondences` appears
in the pretend output and runs without error.

- [ ] **Step 5: Write and run a quick regression test for the new columns**

Add to `tests/Feature/Correspondence/CorrespondenceWorkflowTest.php` (after the existing
`test_a_valid_close_stamps_the_actual_close_date_and_records_an_event` test):

```php
public function test_the_three_new_columns_are_mass_assignable_and_cast_correctly(): void
{
    [, $c] = $this->correspondence();

    $c->update([
        'status' => 'others',
        'other_status_text' => 'Awaiting site visit',
        'client_closed_date' => '2026-09-20',
        'consultant_closed_date' => '2026-09-25',
    ]);

    $fresh = $c->fresh();
    $this->assertSame('Awaiting site visit', $fresh->other_status_text);
    $this->assertSame('2026-09-20', $fresh->client_closed_date->format('Y-m-d'));
    $this->assertSame('2026-09-25', $fresh->consultant_closed_date->format('Y-m-d'));
}
```

Run:

```bash
php artisan test --filter CorrespondenceWorkflowTest
```

Expected: all tests in the file pass, including the new one.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_09_15_000004_add_status_extension_to_project_correspondences.php \
  app/Models/ProjectCorrespondence.php tests/Feature/Correspondence/CorrespondenceWorkflowTest.php
git commit -m "feat(correspondence): add forwarded/others status support and two close-date columns"
```

---

### Task 2: Backend validation, service behavior, and the close-bypass fix

**Files:**
- Modify: `app/Http/Controllers/Api/CorrespondenceController.php` (`store`, `update`,
  `changeStatus` methods)
- Modify: `app/Services/CorrespondenceService.php` (`create`, `update` methods)
- Test: `tests/Feature/Correspondence/CorrespondenceWorkflowTest.php`

**Interfaces:**
- Consumes: `ProjectCorrespondence` fillable attributes from Task 1.
- Produces: validated request data always has `other_status_text` correctly nulled when
  `status` isn't `others` — callers of `CorrespondenceService::create()`/`update()` can rely on
  this invariant without checking it themselves.

- [ ] **Step 1: Write the failing tests first**

Add to `tests/Feature/Correspondence/CorrespondenceWorkflowTest.php`:

```php
public function test_others_status_requires_the_free_text_field(): void
{
    [$project] = $this->correspondence();

    $this->actingAs($this->user(['projects.view', 'projects.edit']))
        ->postJson('/api/correspondence', [
            'project_id' => $project->id, 'type' => 'ncr', 'title' => 'X',
            'raised_date' => now()->toDateString(), 'status' => 'others',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('other_status_text');
}

public function test_others_status_with_text_is_accepted_and_stored(): void
{
    [$project] = $this->correspondence();

    $this->actingAs($this->user(['projects.view', 'projects.edit']))
        ->postJson('/api/correspondence', [
            'project_id' => $project->id, 'type' => 'ncr', 'title' => 'X',
            'raised_date' => now()->toDateString(), 'status' => 'others',
            'other_status_text' => 'Awaiting site visit',
        ])
        ->assertCreated()
        ->assertJsonPath('data.other_status_text', 'Awaiting site visit');
}

public function test_forwarded_status_is_accepted(): void
{
    [$project] = $this->correspondence();

    $this->actingAs($this->user(['projects.view', 'projects.edit']))
        ->postJson('/api/correspondence', [
            'project_id' => $project->id, 'type' => 'ncr', 'title' => 'X',
            'raised_date' => now()->toDateString(), 'status' => 'forwarded',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'forwarded');
}

public function test_moving_status_away_from_others_clears_the_free_text(): void
{
    [, $c] = $this->correspondence();
    $c->update(['status' => 'others', 'other_status_text' => 'Awaiting site visit']);

    $this->actingAs($this->user(['projects.view', 'projects.edit']))
        ->postJson("/api/correspondence/{$c->id}", ['_method' => 'PUT', 'status' => 'open'])
        ->assertOk();

    $this->assertNull($c->fresh()->other_status_text);
}

public function test_the_generic_update_endpoint_cannot_set_status_to_closed(): void
{
    [, $c] = $this->correspondence();

    $this->actingAs($this->user(['projects.view', 'projects.edit']))
        ->postJson("/api/correspondence/{$c->id}", ['_method' => 'PUT', 'status' => 'closed'])
        ->assertStatus(422);

    $this->assertSame('open', $c->fresh()->status);
}

public function test_updating_an_already_closed_correspondence_without_changing_its_status_still_works(): void
{
    [, $c] = $this->correspondence();
    ProjectCorrespondenceFile::create(['project_correspondence_id' => $c->id, 'file_path' => 'x/y.pdf', 'file_name' => 'y.pdf']);
    $c->update(['status' => 'closed', 'closing_reference' => 'CLOSE-1', 'actual_close_date' => now()->toDateString()]);

    $this->actingAs($this->user(['projects.view', 'projects.edit']))
        ->postJson("/api/correspondence/{$c->id}", ['_method' => 'PUT', 'status' => 'closed', 'title' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Renamed');
}

public function test_client_and_consultant_close_dates_are_settable_via_update(): void
{
    [, $c] = $this->correspondence();

    $this->actingAs($this->user(['projects.view', 'projects.edit']))
        ->postJson("/api/correspondence/{$c->id}", [
            '_method' => 'PUT', 'client_closed_date' => '2026-09-20', 'consultant_closed_date' => '2026-09-25',
        ])
        ->assertOk()
        ->assertJsonPath('data.client_closed_date', '2026-09-20')
        ->assertJsonPath('data.consultant_closed_date', '2026-09-25');
}
```

- [ ] **Step 2: Run the tests to confirm they fail**

```bash
php artisan test --filter CorrespondenceWorkflowTest
```

Expected: the seven new tests FAIL (422 vs 201/200 mismatches, or validation not yet accepting
`forwarded`/`others`, or the closed-status guard not yet in place).

- [ ] **Step 3: Write the qwen task prompt for the controller + service changes**

Create `storage/app/qwen-tasks/correspondence-status-extension-backend.txt`:

```
TASK: Update validation and service logic in the Correspondence backend for two new statuses
and a bug fix, per the failing tests already written in
tests/Feature/Correspondence/CorrespondenceWorkflowTest.php (shown in context).

1. app/Http/Controllers/Api/CorrespondenceController.php — `store` method:
   - Change the `'status' => ['nullable', 'in:open,pending,closed,declined']` rule to
     `'status' => ['nullable', 'in:open,pending,declined,forwarded,others']` (removes 'closed'
     entirely — a brand new correspondence can never be created pre-closed).
   - Add a new rule: `'other_status_text' => ['required_if:status,others', 'nullable', 'string', 'max:255']`.

2. app/Http/Controllers/Api/CorrespondenceController.php — `update` method:
   - Replace the `'status' => ['sometimes', 'in:open,pending,closed,declined']` rule with a
     closure-based rule that: accepts open/pending/declined/forwarded/others unconditionally;
     for the value 'closed', looks up the CURRENT status of the correspondence being updated
     (`ProjectCorrespondence::whereKey($id)->value('status')`) — if it is already 'closed', allow
     the value through (no-op, doesn't force a reopen just to edit other fields); otherwise fail
     validation with the message 'Use the dedicated close action to close a correspondence (it
     requires a reference and an attachment).'; any other value fails with 'The selected status
     is invalid.'. Use the closure-rule signature `function ($attribute, $value, $fail) use ($id) { ... }`
     inside the `'status' => ['sometimes', function (...) { ... }]` array.
   - Add `'other_status_text' => ['required_if:status,others', 'nullable', 'string', 'max:255']`.
   - Add `'client_closed_date' => ['nullable', 'date']` and
     `'consultant_closed_date' => ['nullable', 'date']`.

3. app/Http/Controllers/Api/CorrespondenceController.php — `changeStatus` method (the
   `/correspondence/{id}/status` workflow endpoint, separate from store/update):
   - Change `'status' => ['required', 'in:open,pending,closed,declined']` to
     `'status' => ['required', 'in:open,pending,closed,declined,forwarded,others']` — this
     endpoint is a deliberate, explicit status-change action (not the generic edit form), so it
     may still set 'closed' directly, exactly as it could before this change; only extend it
     with the two new values, don't restrict 'closed' here.

4. app/Services/CorrespondenceService.php — both `create()` and `update()` methods: before
   `ProjectCorrespondence::create($data)` / `$correspondence->update($data)`, add this rule: if
   `$data` contains a 'status' key and its value is not 'others', force
   `$data['other_status_text'] = null` (so stale free text never lingers once the status moves
   away from 'others'). Do NOT touch `other_status_text` if the 'status' key is absent from
   `$data` entirely (e.g. a request that only updates the title shouldn't clear it).

Do not change anything else in these three files — in particular, don't touch the `store`
method's other fields, the `close`/`reopen`/`handOver`/`note` methods, or anything in
CorrespondenceService.php beyond the one small addition to create() and update().

OUTPUT: a diff per file (old line(s) -> new line(s)) with enough surrounding context to apply
each change unambiguously.
```

- [ ] **Step 4: Run qwen and review the output**

```bash
php artisan qwen:agent storage/app/qwen-tasks/correspondence-status-extension-backend.txt \
  --context=app/Http/Controllers/Api/CorrespondenceController.php,app/Services/CorrespondenceService.php,tests/Feature/Correspondence/CorrespondenceWorkflowTest.php \
  --out=storage/app/qwen-tasks/correspondence-status-extension-backend-out.md
```

Read the output. Verify carefully: the `update` closure rule must correctly special-case an
already-closed record (test 6 above), the `changeStatus` method must still allow `closed`
unconditionally (it's a different, deliberate action), and the `other_status_text`-clearing logic
must only fire when `status` is present in `$data`.

- [ ] **Step 5: Apply the reviewed diff**

Use Edit to apply each reviewed change to the three files. Fix anything qwen got wrong (e.g. if
the closure rule doesn't handle the "already closed, no-op" case correctly) before applying.

- [ ] **Step 6: Run the tests to confirm they pass**

```bash
php artisan test --filter CorrespondenceWorkflowTest
```

Expected: all tests in the file PASS (the 7 new ones plus every pre-existing one — in particular
re-verify `test_a_valid_close_stamps_the_actual_close_date_and_records_an_event`, which goes
through the *workflow* close endpoint and must be unaffected).

- [ ] **Step 7: Run Pint on the changed PHP files**

```bash
php vendor/bin/pint app/Http/Controllers/Api/CorrespondenceController.php app/Services/CorrespondenceService.php --test
```

Expected: `pass`. If not, run without `--test` to auto-fix, then re-run the test suite.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Api/CorrespondenceController.php app/Services/CorrespondenceService.php \
  tests/Feature/Correspondence/CorrespondenceWorkflowTest.php
git commit -m "fix(correspondence): block closing via generic update, add forwarded/others validation"
```

---

### Task 3: PDF report updates

**Files:**
- Modify: `resources/views/pdf/correspondence.blade.php`
- Test: `tests/Feature/Correspondence/CorrespondenceWorkflowTest.php`

**Interfaces:**
- Consumes: `$c->status`, `$c->other_status_text`, `$c->client_closed_date`,
  `$c->consultant_closed_date` (all available on `ProjectCorrespondence` after Task 1).

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Correspondence/CorrespondenceWorkflowTest.php`:

```php
public function test_pdf_export_renders_for_an_others_status_correspondence_with_both_close_dates(): void
{
    [, $c] = $this->correspondence();
    $c->update([
        'status' => 'others', 'other_status_text' => 'Awaiting site visit',
        'client_closed_date' => '2026-09-20', 'consultant_closed_date' => '2026-09-25',
    ]);
    CorrespondenceEvent::create(['project_correspondence_id' => $c->id, 'event_type' => 'raised', 'to_status' => 'open']);

    $res = $this->actingAs($this->user(['projects.view']))->get("/api/correspondence/{$c->id}/pdf");
    $res->assertOk();
    $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
}
```

- [ ] **Step 2: Run the test to confirm it fails or passes for the wrong reason**

```bash
php artisan test --filter test_pdf_export_renders_for_an_others_status_correspondence_with_both_close_dates
```

If dompdf silently ignores an undefined `$c->badge-others` CSS class this may pass even before
the template is updated (Blade doesn't error on rendering plain text) — that's fine, this is a
smoke test for rendering, not a content assertion. Proceed to Step 3 regardless so the PDF
actually surfaces the new fields to a human reader.

- [ ] **Step 3: Write the qwen task prompt for the Blade template changes**

Create `storage/app/qwen-tasks/correspondence-pdf-status-extension.txt`:

```
TASK: Update resources/views/pdf/correspondence.blade.php (shown in context) to surface two new
close-date fields and two new status values.

1. In the `<style>` block, alongside the existing `.badge-open`, `.badge-pending`,
   `.badge-closed`, `.badge-declined` classes, add:
   `.badge-forwarded { background: #e0e7ff; color: #3730a3; }`
   `.badge-others { background: #f3f4f6; color: #374151; }`

2. Find this line (the status badge in the Details section):
   `<div class="row"><div class="label">Status</div><div class="value"><span class="badge badge-{{ $c->status }}">{{ $c->status }}</span></div></div>`
   Replace it with:
   `<div class="row"><div class="label">Status</div><div class="value"><span class="badge badge-{{ $c->status }}">{{ $c->status === 'others' ? $c->other_status_text : $c->status }}</span></div></div>`

3. Immediately after the existing row:
   `<div class="row"><div class="label">Actual close</div><div class="value">{{ optional($c->actual_close_date)->format('d M Y') ?? '—' }}</div></div>`
   add two new conditional rows (only rendered when the date is set):
   ```
   @if ($c->client_closed_date)
   <div class="row"><div class="label">Client closed</div><div class="value">{{ $c->client_closed_date->format('d M Y') }}</div></div>
   @endif
   @if ($c->consultant_closed_date)
   <div class="row"><div class="label">Consultant closed</div><div class="value">{{ $c->consultant_closed_date->format('d M Y') }}</div></div>
   @endif
   ```

Do not change anything else in the file.

OUTPUT: the three diffs (old -> new) with surrounding context.
```

- [ ] **Step 4: Run qwen and review the output**

```bash
php artisan qwen:agent storage/app/qwen-tasks/correspondence-pdf-status-extension.txt \
  --context=resources/views/pdf/correspondence.blade.php \
  --out=storage/app/qwen-tasks/correspondence-pdf-status-extension-out.md
```

Verify the badge-label line correctly falls back to `$c->status` for every status except
`others`, and that the two new rows use `@if` guards (not `@if ($c->client_closed_date !== null)`
verbosity — either is fine functionally, just confirm it doesn't error when null).

- [ ] **Step 5: Apply the reviewed diff**

- [ ] **Step 6: Run the PDF test**

```bash
php artisan test --filter CorrespondenceWorkflowTest
```

Expected: all tests pass, including both PDF tests (`test_pdf_export_renders` and the new one).

- [ ] **Step 7: Commit**

```bash
git add resources/views/pdf/correspondence.blade.php tests/Feature/Correspondence/CorrespondenceWorkflowTest.php
git commit -m "feat(correspondence): show forwarded/others status and close-date checkpoints in PDF"
```

---

### Task 4: Frontend — Correspondence.jsx status handling

**Files:**
- Modify: `resources/js/pages/projects/correspondence/Correspondence.jsx`

**Interfaces:**
- Consumes: backend `status` values from Task 2 (`open|pending|closed|declined|forwarded|others`)
  and `other_status_text` field.
- Produces: no new exports — this is a leaf page component.

- [ ] **Step 1: Write the qwen task prompt**

Create `storage/app/qwen-tasks/correspondence-frontend-status.txt`:

```
TASK: Add 'forwarded' and 'others' status support to the Correspondence list/edit page
(resources/js/pages/projects/correspondence/Correspondence.jsx, shown in context).

1. The `statusColors` object (near the top of the file, alongside `open`/`pending`/`closed`/
   `declined` keys) gets two new entries:
   `forwarded: 'bg-indigo-100 text-indigo-700',`
   `others: 'bg-gray-200 text-gray-800',`

2. `baseForm` (the object with `status: 'open', raised_date: ..., due_date: ..., response: ...,
   files: []`) gains a new field: `other_status_text: '',` right after `status: 'open',`.

3. The `openEdit` function's `setForm({...})` call currently includes
   `title: item.title || '', description: item.description || '', status: item.status || 'open',`
   — add `other_status_text: item.other_status_text || '',` right after the `status:` line in
   that same object.

4. The status `<select>` in the New/Edit form (with options Open/Pending/Closed/Decline) gains
   two more `<option>` elements: `<option value="forwarded">Forwarded</option>` and
   `<option value="others">Others</option>`, added after the existing `declined` option.
   Immediately after that `<select>` element's closing tag (still inside the same wrapping
   `<div>` that has the "Status" label), add a conditionally-rendered text input that only shows
   when `form.status === 'others'`:
   ```jsx
   {form.status === 'others' && (
       <input type="text" placeholder="Describe the status *" value={form.other_status_text}
           onChange={(e) => setForm((p) => ({ ...p, other_status_text: e.target.value }))}
           required className="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
   )}
   ```

5. In `handleSubmit`, right after the line `fd.append('status', form.status);`, add:
   ```jsx
   if (form.status === 'others' && form.other_status_text) fd.append('other_status_text', form.other_status_text);
   ```

6. The status FILTER `<select>` (the one with `<option value="">All Statuses</option>` followed
   by Open/Pending/Closed/Decline options) gains the same two new options as step 4, added after
   the `declined` option: `<option value="forwarded">Forwarded</option>` and
   `<option value="others">Others</option>`.

7. In the list table body, find this line:
   `<td className="px-4 py-3"><span className={\`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[item.status] || 'bg-gray-100 text-gray-600'}\`}>{item.status}</span></td>`
   Replace `{item.status}` (the badge's visible text, NOT the className expression) with
   `{item.status === 'others' && item.other_status_text ? item.other_status_text : item.status}`
   — keep everything else in that line exactly as-is.

Do not touch any other part of the file (the `BADGE_COLORS`/`DOT_COLORS`/`PALETTE` constants are
for correspondence TYPE colors, not status — leave those alone).

OUTPUT: a diff per numbered change above (old -> new) with a couple of lines of surrounding
context, in order.
```

- [ ] **Step 2: Run qwen and review the output**

```bash
php artisan qwen:agent storage/app/qwen-tasks/correspondence-frontend-status.txt \
  --context=resources/js/pages/projects/correspondence/Correspondence.jsx \
  --out=storage/app/qwen-tasks/correspondence-frontend-status-out.md
```

Verify all 7 changes are present, the conditional `other_status_text` input only renders for
`status === 'others'`, and nothing in the TYPE-related `BADGE_COLORS`/`DOT_COLORS`/`PALETTE`
constants was touched.

- [ ] **Step 3: Apply the reviewed diff**

Read the current file first (it changed since the last read, from the drag-scroll work earlier
today), locate each of the 7 target spots by content (not by trusting old line numbers), and
apply with Edit.

- [ ] **Step 4: Build and smoke-check**

```bash
npm run build
```

Expected: build succeeds with no errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/projects/correspondence/Correspondence.jsx
git commit -m "feat(correspondence): add Forwarded/Others status options to list and edit form"
```

---

### Task 5: Frontend — CorrespondenceWorkflowDrawer.jsx close-date checkpoints

**Files:**
- Modify: `resources/js/pages/projects/correspondence/CorrespondenceWorkflowDrawer.jsx`
- Modify: `resources/js/services/correspondenceService.js`

**Interfaces:**
- Produces: `correspondenceService.updateCloseDates(id, { client_closed_date, consultant_closed_date })`
  — a thin wrapper around the existing generic `update()` method, used only by the drawer.

- [ ] **Step 1: Write the qwen task prompt**

Create `storage/app/qwen-tasks/correspondence-drawer-close-dates.txt`:

```
TASK: Add client/consultant close-date checkpoints to the correspondence workflow drawer.

1. resources/js/services/correspondenceService.js (shown in context) — add a new method to the
   exported `correspondenceService` object, placed right after the existing `close(...)` method
   and before `reopen(...)`:
   ```js
   async updateCloseDates(id, { client_closed_date, consultant_closed_date }) {
       const formData = new FormData();
       if (client_closed_date !== undefined) formData.append('client_closed_date', client_closed_date || '');
       if (consultant_closed_date !== undefined) formData.append('consultant_closed_date', consultant_closed_date || '');
       return this.update(id, formData);
   },
   ```

2. resources/js/pages/projects/correspondence/CorrespondenceWorkflowDrawer.jsx (shown in
   context) — in the info grid near the top of the detail view, which currently has this exact
   block:
   ```jsx
   <div className="grid grid-cols-2 gap-3 rounded-lg bg-gray-50 p-3 text-sm">
       <div><span className="text-xs uppercase text-gray-400">Status</span><div className="font-medium capitalize">{detail.status}</div></div>
       <div><span className="text-xs uppercase text-gray-400">Currently at</span><div className="font-medium">{detail.current_party?.name || '—'}</div></div>
       <div><span className="text-xs uppercase text-gray-400">Expected close</span><div>{detail.expected_close_date || '—'}</div></div>
       <div><span className="text-xs uppercase text-gray-400">Actual close</span><div>{detail.actual_close_date || '—'}</div></div>
       {detail.closing_reference && <div className="col-span-2"><span className="text-xs uppercase text-gray-400">Closing ref</span><div>{detail.closing_reference}</div></div>}
   </div>
   ```
   - Change the Status line's `{detail.status}` to
     `{detail.status === 'others' && detail.other_status_text ? detail.other_status_text : detail.status}`
     (keep `capitalize` className as-is even though 'others' text may include mixed case — no
     other change to that div).
   - Add two new editable rows after the "Actual close" div and before the `closing_reference`
     conditional div, only interactive when `canEdit` is true (read-only text otherwise, same
     pattern as the rest of the component which gates editing on `canEdit`):
     ```jsx
     <div>
         <span className="text-xs uppercase text-gray-400">Client closed</span>
         {canEdit ? (
             <input type="date" defaultValue={detail.client_closed_date || ''}
                 onBlur={(e) => { if (e.target.value !== (detail.client_closed_date || '')) run(() => correspondenceService.updateCloseDates(id, { client_closed_date: e.target.value }), 'Client close date updated'); }}
                 className="mt-0.5 w-full rounded border border-gray-200 px-1.5 py-1 text-sm" />
         ) : <div>{detail.client_closed_date || '—'}</div>}
     </div>
     <div>
         <span className="text-xs uppercase text-gray-400">Consultant closed</span>
         {canEdit ? (
             <input type="date" defaultValue={detail.consultant_closed_date || ''}
                 onBlur={(e) => { if (e.target.value !== (detail.consultant_closed_date || '')) run(() => correspondenceService.updateCloseDates(id, { consultant_closed_date: e.target.value }), 'Consultant close date updated'); }}
                 className="mt-0.5 w-full rounded border border-gray-200 px-1.5 py-1 text-sm" />
         ) : <div>{detail.consultant_closed_date || '—'}</div>}
     </div>
     ```
   - The `<div className="grid grid-cols-2 ...">` wrapper's contents therefore grow from 4/5
     items to 6/7 — do not change the wrapper's own className or grid setup, it already wraps
     correctly at grid-cols-2.

Do not change the `run` helper, the timeline/history rendering, or any of the hand-over/close/
reopen action buttons — those are unrelated to this task.

OUTPUT: the new correspondenceService.js method (full method block) and the full replacement
info-grid JSX block for the drawer, so both can be applied as straightforward find-and-replace.
```

- [ ] **Step 2: Run qwen and review the output**

```bash
php artisan qwen:agent storage/app/qwen-tasks/correspondence-drawer-close-dates.txt \
  --context=resources/js/services/correspondenceService.js,resources/js/pages/projects/correspondence/CorrespondenceWorkflowDrawer.jsx \
  --out=storage/app/qwen-tasks/correspondence-drawer-close-dates-out.md
```

Verify: `updateCloseDates` only appends fields that are actually passed (so calling it with just
`client_closed_date` doesn't clobber `consultant_closed_date` by sending an empty string for it —
check the `!== undefined` guards are present), and the drawer's date inputs use `onBlur` (not
`onChange`) so it doesn't fire a network request on every keystroke.

- [ ] **Step 3: Apply the reviewed diff**

- [ ] **Step 4: Build and smoke-check**

```bash
npm run build
```

Expected: build succeeds with no errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/services/correspondenceService.js resources/js/pages/projects/correspondence/CorrespondenceWorkflowDrawer.jsx
git commit -m "feat(correspondence): add client/consultant close-date checkpoints to workflow drawer"
```

---

### Task 6: Full verification, deploy

**Files:** none (verification only)

- [ ] **Step 1: Run the full backend test suite**

```bash
php artisan test
```

Expected: full suite passes (no regressions outside the Correspondence tests touched in this plan).

- [ ] **Step 2: Run Pint across the whole app**

```bash
php vendor/bin/pint --test
```

Expected: `pass`. If not, run without `--test`, review the diff, re-run step 1.

- [ ] **Step 3: Final frontend build**

```bash
npm run build
```

Expected: succeeds; note the new hashed filenames in `public/build/manifest.json`.

- [ ] **Step 4: Stage and commit the build output**

```bash
git add public/build/
git status --short
```

Confirm only `public/build/**` (renamed/changed hashed assets + manifest.json) is staged, then:

```bash
git commit -m "chore(correspondence): rebuild frontend assets for status/close-date extension"
```

- [ ] **Step 5: Push and report**

```bash
git push origin main
```

Report to the user: summarize what changed, remind them this still needs
`git pull && bash deploy.sh` on cPanel (or do it directly via the established SSH workflow if the
user asks, exactly as done earlier this session), and call out explicitly that the fix in Task 2
means any *existing* correspondence a user tries to edit-and-save while it's mid-transition to
`closed` via the plain form will now get a 422 — they must use the dedicated "Close…" button in
the workflow drawer instead. This is the intended fix, but worth flagging as a visible behavior
change.
