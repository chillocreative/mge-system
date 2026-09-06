# MGE-PMS — Fasa 0 Code Audit

**Date:** 7 September 2026 (overnight autonomous run)
**Audited against:** `/Users/m4/Downloads/MGE_SYSTEM_PLAN.md` v0.2 (287,692 bytes, verified complete)
**Purpose:** The plan is written from *screenshots only* — its author explicitly says "Saya belum lihat kod sebenar, jadi ini andaian kerja." This document reconciles those assumptions against the actual codebase, so no table gets built twice and no schema gets overwritten.

> **Read this before MGE_PLAN_EXECUTION.md.** Several of the plan's proposed tables **already exist**, and several of its "must decide" questions are already answered by the code.

---

## 1. Environment (verified working)

| Item | Value |
|---|---|
| PHP | 8.4.23 |
| Composer | 2.10.2 · `vendor/` present |
| Laravel | 12.53.0 |
| DB | MySQL 9.7.1 @ 127.0.0.1 · schema `mge_pms` · reachable |
| Node / npm | v22.23.1 / 10.9.8 · `node_modules/` present |
| Migrations | 83 files |
| Models | 69 files |
| Test suite | **2 example tests only** — no real coverage |
| Test DB | sqlite `:memory:` (phpunit.xml) — safe, isolated |
| Baseline `php artisan test` | ✅ 2 passed |

### 1.1 🔴 The local database is NOT a copy of production

| Table | Local rows |
|---|---|
| `employees` | **1** |
| `leave_requests` | **1** |
| `leave_balances` | **0** |
| `projects` | **1** |
| `project_members` | **0** |
| `site_logs` | **1** |
| `users` | 6 |

The plan's screenshots came from **production** (`app.mge-eng.com`), which has real staff data. Locally there is essentially nothing.

**Consequence:** every *data* task in the plan — investigating the orphan leave record (0.3 #13), marking test records inactive (27.12), filling `Reporting Manager`, loading opening balances (27.4) — **cannot be done or verified from this machine.** They need a production DB dump.

Every *code* task (schema, engine, tests, UI) can proceed normally.

---

## 2. Answers to the plan's open questions, from the code

### 2.1 Section 0 assumptions (A1–A7)

| # | Plan assumption | Verdict |
|---|---|---|
| A1 | Laravel 11.x, MySQL or PostgreSQL | ⚠️ **Laravel 12.53**, MySQL 9.7 |
| A2 | Blade + Livewire / Inertia+Vue / Filament | ❌ **None of these.** React 19 SPA + Vite 7 + Tailwind 4, Sanctum cookie auth, React Router 7. Backend is a pure JSON API (`routes/api.php`). |
| A3 | `Projects` has `Correspondence` and `Site Logs` | ✅ Both exist (`project_correspondences`, `site_logs`) |
| A4 | Role/permission system exists | ✅ Spatie, **plus** a `2026_06_23_120001_convert_to_per_user_permissions` migration — permissions are per-user, not purely role-based |
| A5 | user ↔ project pivot exists | ✅ **`project_members` EXISTS** — see 2.2 |
| A6 | Queue worker still `sync` | ❌ **`QUEUE_CONNECTION=database`** already. Jobs tables exist. |
| A7 | Mail driver | Local `.env` = `log`. Production unverified from here. |

### 2.2 ⭐ P1 — does `project_members` exist? **YES**

`database/migrations/2024_01_01_000005_create_project_members_table.php` + `app/Models/ProjectMember.php`.

The plan lists P1 as one of "eight things blocking us from starting", and makes **Ciri 3, 14, 15 and 20** depend on it (Fasa 2b). **That blocker is already cleared.** It has 0 rows locally, but the structure is there.

### 2.3 ⭐ AB2 / H10 — does `leave_balances` exist? **YES, but thin**

```
leave_balances: id, employee_id, leave_type_id, year,
                entitled_days, used_days, remaining_days, timestamps
                unique(employee_id, leave_type_id, year)
```

Compare against what §7.2 of the plan requires:

| §7.2 column | Present? |
|---|---|
| `entitled_days` (snapshot) | ✅ |
| `carried_forward` | ❌ |
| `adjustment_days` (manual admin +/-) | ❌ |
| `taken_days` | ✅ (named `used_days`) |
| `balance_days` | ✅ (named `remaining_days`) |
| `rule_snapshot` (json) | ❌ **the important one** |
| `calculated_at` | ❌ |
| `is_locked` (§7.3.3c) | ❌ |

➡️ **Extend, don't recreate.** Renaming `used_days`/`remaining_days` would break live code for no benefit — keep the existing names.

### 2.4 ⭐ Ciri 9 — employment status: **mostly already built**

`employees.status` is already `enum('active','inactive','resigned')`, and the model uses `SoftDeletes`.

The plan treats Ciri 9 as a new feature (Fasa 2). In reality what is missing is narrower:
- `last_working_date` (§9.4 — distinct from the existing `resign_date`) ❌
- automatic login disable when status ≠ active ❌ (needs verification in `EmployeeService`/`AuthService`)
- status-change audit trail ❌
- `resignation_reason` / `cancelled_by`-style provenance ❌

### 2.5 🔴 Root cause of the orphan leave record (0.3 #13) — high-confidence hypothesis

```php
// employees table
$table->softDeletes();

// leave_requests table
$table->foreignId('employee_id')->constrained()->cascadeOnDelete();
```

`cascadeOnDelete` is a **database-level** FK action. A Laravel **soft** delete is an `UPDATE`, not a `DELETE` — so it never fires the cascade. A soft-deleted employee therefore leaves its `leave_requests` rows intact, and `LeaveRequest::with('employee')` returns `null` for them.

➡️ **That renders exactly as a leave row with a blank Employee column** — precisely what the plan documents at 0.3 #13.

This is consistent, cheap to confirm, and cannot be proven from this machine (0 soft-deleted employees locally). **Confirm against production with:**

```sql
SELECT lr.id, lr.employee_id, lr.start_date, lr.days_count, lr.status,
       e.employee_no, e.deleted_at
FROM leave_requests lr
LEFT JOIN employees e ON e.id = lr.employee_id
WHERE e.id IS NULL OR e.deleted_at IS NOT NULL;
```

If `deleted_at` is populated → hypothesis confirmed, and the fix is a display/scope decision, not data repair. **Answers AB18.**

### 2.6 🔴 The leave day calculation is currently raw calendar days

`app/Services/LeaveService.php:apply()`:

```php
$days = $halfDay ? 0.5 : ($start->diffInDays($end) + 1);
```

No rest days. No public holidays. No per-employee work pattern. No cross-year split. **No balance check at all** — an employee can apply for 100 days of AL and nothing stops them. `requires_attachment` exists on `leave_types` but `apply()` never enforces it.

This single line is what §27.6b, §27.13, and the whole "WAJIB" list of §27.3 exist to replace. It is the core of the work.

### 2.7 Approval flow — already two-level

Already built, and it does **not** match the plan's assumption of `Reporting Manager → Director`:

- `leave_types.requires_director_approval`, `manager_approver_id`, `director_approver_id`
- `leave_requests.current_approval_level` (`manager`|`director`), with per-stage approver + timestamp columns
- `users.is_manager`, `users.is_director` flags
- `LeaveService::authorizeStage()` enforces it

➡️ **Approval routing is per *leave type*, not per *employee's reporting manager*.** `employees.reporting_manager_id` exists but the leave flow ignores it entirely.

⚠️ This partially **invalidates blocker #2 of §27.2** ("Reporting Manager kosong → permohonan tiada pelulus"). Requests do not get stuck today, because approval falls back to any `is_manager` user. Whether that is *desired* is a business question for Rahim — see MGE_PROGRESS.md.

### 2.8 Which of the plan's proposed tables already exist

| Plan proposes | Status |
|---|---|
| `project_members` (Ciri 15) | ✅ **exists** |
| `leave_balances` (Ciri 7) | ✅ exists, needs extending |
| `leave_types` + `requires_attachment` (7.3.6) | ✅ exists |
| `notifications` (Ciri 3, 26.2) | ✅ exists + `NotificationService` + `LeaveStatusNotification` |
| `site_log_machinery` (Ciri 2/21) | ✅ exists |
| `site_log_weather_events` (Ciri 2, "Rain Stop") | ✅ exists — **answers C11** |
| `vehicles`, `inventory_items` (Ciri 21/22) | ✅ exist |
| `drawings` + `contract_id` (Ciri 4) | ✅ exists |
| `contract_boq_items` (Ciri 5) | ✅ exists |
| `correspondence_types` (Ciri 1) | ✅ exists — **answers Q10** |
| `memos` + `memo_attachments` (Ciri 11) | ✅ exist |
| `contract_pics` (Ciri 3 recipients) | ✅ exists |
| **`public_holidays`** | ❌ missing |
| **`work_patterns`** | ❌ missing |
| **`leave_days`** (27.13) | ❌ missing |
| **`leave_entitlement_rules`** (7.2) | ❌ missing |
| **`leave_policy_settings`** (7.3.1) | ❌ missing |
| **`leave_quota_pools`** (7.3.6) | ❌ missing |
| **`staff_leave_entitlement_overrides`** (7.2) | ❌ missing |

➡️ **Six new tables is the entire P1 schema scope.** Everything else in the Leave block is engine, seed data, and UI on top of structures that already exist.

### 2.9 Misc answered

| # | Question | Answer from code |
|---|---|---|
| C11 | Is there a `Rain Stop` as well as `Rain Start`? | ✅ Yes — `site_log_weather_events` table |
| Q10 | Correspondence types manageable? | ✅ `correspondence_types` table exists |
| M1 | Real frontend stack? | React 19 SPA + Vite 7 + Tailwind 4 (**not** Blade/Livewire/Inertia/Filament) |
| AB7 | Every staff member has a login? | `employees.user_id` is **nullable** — the schema permits staff without logins, whatever the current data says |
| E4 | Storage — VPS disk or S3/Spaces? | `FILESYSTEM_DISK=local` |
| S7 | Chromium on VPS for PDF? | Not needed — `barryvdh/laravel-dompdf` already installed |
| **C8** | **Is the `Monthly Report` button actually working?** ⭐ | ✅ **Yes.** `routes/api.php` has `GET projects/{project}/site-logs/report/pdf` → `SiteLogController@monthlyReportPdf`. The plan lists C8 as one of the eight things blocking a start and as the question that "determines the scope of Ciri 2". It is implemented — Ciri 2 is an improvement to an existing report, not a build |

---

## 3. Net effect on the plan

**The plan overestimates the remaining work in several places** because it was written without code access:

1. `project_members` exists → the Ciri 3 / 14 / 15 / 20 dependency chain is already unblocked.
2. Employment status largely exists → Ciri 9 is a small extension, not a build.
3. Two-level approval exists → §27.3's "Aliran kelulusan" row is mostly done.
4. Notifications infrastructure exists → §27.3's "Notification dalam app" row is mostly done.
5. Queue is already `database`, not `sync` → the "hidden dependency" note in §28 is moot.

**And it underestimates one thing:** there is **no test coverage whatsoever** (2 example tests). The plan's §27.6/§27.7 edge-case list is essentially a test plan for a leave engine that currently has zero tests protecting it. Building the engine test-first is not optional here — it is the only mechanism available to verify correctness before this touches real staff.
