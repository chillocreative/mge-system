# MGE-PMS — Implementation Execution Plan

**Author:** Claude (lead engineer), autonomous overnight run, 7 September 2026
**Source plan:** `/Users/m4/Downloads/MGE_SYSTEM_PLAN.md` v0.2 — verified complete (287,692 bytes, 32 sections)
**Prerequisite reading:** [MGE_AUDIT.md](MGE_AUDIT.md) — what already exists in the code
**Running log:** [MGE_PROGRESS.md](MGE_PROGRESS.md) — what actually got done, decisions, blockers

---

## Governing constraints

| Constraint | Consequence for this plan |
|---|---|
| **Production is live with real staff data** (plan 0.4 #22) | No destructive migrations. Additive only. Every phase independently rollback-able. |
| **No push, no deploy** — Rahim approves | All work is committed **locally** only. |
| **Local DB is near-empty, not a prod copy** (audit 1.1) | Correctness is proved by **tests**, not by eyeballing local data. Data-cleanup tasks are deferred, not attempted. |
| **Zero existing test coverage** (audit 1) | The leave engine is built test-first. This is the only available correctness mechanism. |
| Plan is Malay, code/comments are English | Docs for Rahim in English; code follows existing repo conventions. |

---

## Priority: why the Leave module and nothing else

The source plan's §28 lists a 23-phase roadmap covering 25 features. Its own §27 then overrides the ordering:

> *"awak mahu modul Leave digunakan sepenuhnya di production. Jadi **Blok Cuti dinaikkan ke keutamaan pertama**"*

And §27.14 sets the delivery model: **not a launch, a staged tuning of a live system**, in five risk-ordered stages P1→P5.

Everything else in the plan (Correspondence, Drawings, BQ, Safety, Assets) is deferred. Attempting breadth here would produce 25 half-features and no working leave calculation.

**The single highest-value, highest-risk line of code in the system today** is `LeaveService.php:apply()`:

```php
$days = $halfDay ? 0.5 : ($start->diffInDays($end) + 1);
```

Raw calendar days. No rest days, no public holidays, no balance check. Everything below exists to replace that correctly.

---

## Mapping to the source plan's stages

| Source plan | This plan |
|---|---|
| Fasa 0 / L1 — code audit | **Phase A** ✅ |
| P1 — new tables only, nothing uses them yet (🟢 near-zero risk) | **Phase B** |
| P2 — seed data | **Phase C** |
| L6 — calculation engine + edge-case tests (27.7) | **Phase D** |
| L9 — apply form, balance enforcement, MC attachment | **Phase E** |
| P3 — shadow mode: calculate silently, don't display | **Phase F** |
| L7 — policy/tier admin settings | **Phase G** |
| L9/Ciri 8 — balance display, day breakdown | **Phase H** |
| P4/P5 — HR-only, then all staff | **Rahim's call. Not automated.** |
| Fasa 0b / L2 / L8 — data cleanup, opening balances | **BLOCKED** — needs prod dump |

---

## Phases

Each phase is independently runnable and independently verifiable. Each ends with a local commit using explicit paths only.

### Phase A — Code audit ✅ DONE
**Output:** `MGE_AUDIT.md`
**Verify:** n/a
**Key result:** six tables to build, not the ~fifteen the plan implies. `project_members`, employment status, two-level approval and notifications already exist.

---

### Phase B — P1 schema (additive only)
**Risk:** 🟢 near-zero — new tables, nothing reads them yet.
**Delegate to qwen:** yes (migration boilerplate + models), Claude reviews & applies.

New tables:

| Table | Source | Purpose |
|---|---|---|
| `work_patterns` | 27.6b | Office 5-day / Site 6-day rest-day patterns |
| `public_holidays` | 27.2 #3 | national + state, per year |
| `leave_policy_settings` | 7.3.1 | 12 admin-tunable settings, global + per-leave-type override |
| `leave_entitlement_rules` | 7.2 | service-year tiers (`min ≤ service < max`) |
| `leave_quota_pools` | 7.3.6 | MC + Hospitalisation combined 60-day cap |
| `leave_days` | 27.13 | **one row per leave day** — solves cross-year, rest days, holidays, half-days in one structure |

Extensions to existing tables (audit 2.3 — extend, never rename):
- `leave_balances` += `carried_forward`, `adjustment_days`, `rule_snapshot` (json), `calculated_at`, `is_locked`
- `leave_types` += `quota_pool_id` (nullable FK)
- `employees` += `work_pattern_id` (nullable FK — override; default resolves from `category`)

**Verify:** `php artisan migrate` on local, `migrate:rollback` clean, `php artisan test`, `php -l` on each new file.

---

### Phase C — Seed data (P2)
**Risk:** 🟢 low. All seeders idempotent (`firstOrCreate`) per plan 7.3.10c — a re-run must never overwrite what HR has tuned.
**Delegate to qwen:** yes (seeder boilerplate), Claude reviews.

- `leave_entitlement_rules` — Employment Act 1955 minimums, **every row flagged `is_seed_default = true`** (plan 7.3.10a) so the UI can warn they are unverified
- `leave_policy_settings` — defaults from plan table 7.3.1
- `leave_quota_pools` — "Medical Leave" pool, 60-day cap, members MC + HL (Mode B, per plan 7.3.9)
- `work_patterns` — Office (Mon–Fri), Site (Mon–Sat, rest Sunday)
- `public_holidays` — **fixed-date holidays only.** See ⚠️ below.

> ⚠️ **I will not invent 2026 Malaysian holiday dates.** Chinese New Year, Hari Raya Aidilfitri/Aidiladha, Deepavali, Wesak, Awal Muharram, Maulidur Rasul, Thaipusam and the Agong's birthday are lunar/gazetted and shift year to year. Seeding a guessed date would silently produce wrong leave deductions for every employee — the exact failure mode §27.1 warns about. Only fixed-date holidays are seeded; the rest must be entered by HR from the official gazette. The admin UI (Phase G) exists for that.

**Verify:** run seeder twice — second run must produce zero changes. Assert in a test.

---

### Phase D — Leave calculation engine ⭐ **Claude codes directly, test-first**
**Risk:** 🔴 highest-value logic in the module. Wrong numbers here mean wrongly-approved leave and wrong final-salary payouts (plan 27.1).
**Delegate:** ❌ **No.** Per global CLAUDE.md this is design-critical, and per the run brief entitlement/money logic is not delegated. qwen may be used for test-fixture boilerplate only.

`app/Services/Leave/LeaveDayCalculator.php` implementing plan 27.6b:

```
deducted days = days in range
              − that employee's rest days (per their work pattern)
              − public holidays applicable to that employee
```

Materialises one `leave_days` row per calendar day with `fraction`, `is_deducted`, `exclusion_reason` (`rest_day` | `public_holiday` | null) and `year` — so cross-year requests split cleanly (plan AB4).

`app/Services/Leave/EntitlementResolver.php` implementing plan 7.2/7.3:
- service years from `hire_date`, boundary convention **`min_years ≤ service < max_years`** (plan 7.3.8 — fixed, non-negotiable, applied in exactly one place)
- new-joiner proration + rounding
- tier crossing mode
- per-employee overrides
- quota-pool effective balance (plan 7.3.7 — display effective, not raw cap)
- writes `rule_snapshot` so later policy edits never retroactively rewrite history (plan 7.3.3b)

**Tests — every edge case from plan 27.7 becomes a test:**

| Case | Expectation |
|---|---|
| Leave spanning employee's rest day | that day not deducted — **per their pattern, not blanket Sat/Sun** |
| Office vs Site staff, identical dates | different deductions — and that is correct |
| Leave spanning a public holiday | not deducted |
| Half day | 0.5 deducted |
| Leave crossing month | counted in correct year |
| Leave crossing year (30 Dec – 3 Jan) | split across two leave years |
| Two pending requests at once | both reduce available balance |
| Cancel after approval | days returned |
| Mid-year joiner | prorated |
| Employee crossing 2-year tier mid-year | follows `tier_crossing` setting |
| MC over type cap but within pool cap | resolved per pool rules |
| Resigned employee | prorated to `last_working_date` |

**Verify:** `php artisan test` — this phase's value *is* the test suite.

---

### Phase E — Wire the engine in (guarded)
**Risk:** 🟡 touches the live application path.
**Claude codes directly** (balance enforcement is the control that stops over-approval).

- `LeaveService::apply()` uses the engine instead of `diffInDays`
- enforce `requires_attachment` — currently declared on `leave_types` but **never checked** (audit 2.6)
- enforce balance; `allow_negative_balance` setting decides block vs warn
- pending requests reduce available balance (`deduct_pending_from_balance`, plan 8.4)
- cancel/reject returns `leave_days` rows
- MC attachment access restricted to the employee, their approver, and HR (plan 27.3 🔴)
- **behind a config flag**, default off, so deploying this changes nothing until switched on

**Verify:** feature tests through the API routes + `php artisan route:list`.

---

### Phase F — Shadow mode (P3) ⭐
**Risk:** 🟢 read-only.

`php artisan leave:recalculate --year=2026 --shadow` — computes every employee's balance, stores it, **displays nothing**, and emits a comparison report against whatever the current numbers say.

Plan 27.14 calls this the single most valuable stage: it validates against *all* employees automatically instead of the 10-employee manual check of §27.6, with zero user impact. It is also the tool Rahim runs against a prod dump to find discrepancies before anyone sees a number.

---

### Phase G — Admin settings API + UI
Policy settings, entitlement tiers, quota pools, work patterns, **public holidays** (needed for HR to enter the gazetted 2026 dates). Includes the "I confirm these entitlements are correct" one-time acknowledgement of plan 7.3.10b.
**Delegate to qwen:** controllers/requests/services boilerplate. Claude reviews, and codes the permission gates directly.

---

### Phase H — Employee-facing display
Apply-form day breakdown (plan 27.6b — *"5 calendar days, 1 rest day, deducted: 4.0"*), balance display per plan 7.3.7. Prevents the "why is his 4 days and mine 5?" dispute before it starts.
**Claude codes directly** — design-critical UI per the run brief.

---

## Out of scope tonight, and why

| Item | Reason |
|---|---|
| Fasa 0b data cleanup, orphan record repair, opening balances | Needs production data. Audit 1.1. |
| P4 / P5 rollout | Irreversible exposure to real staff. Rahim's decision. |
| Any push or deploy | Explicitly withheld. |
| Ciri 1–6, 10–25 (Correspondence, Drawings, BQ, Safety, Assets, Email removal) | Deferred by the source plan's own §27 reprioritisation. |
| Guessing 2026 lunar holiday dates | Would silently corrupt every leave calculation. Phase C. |

---

## Conventions for this run

1. One migration = one logical change (plan 27.14 rule 3).
2. Never drop a column in the same migration that adds one (plan 27.14 rule 4).
3. Never rename an existing live column — extend alongside.
4. `firstOrCreate` in every seeder (plan 7.3.10c).
5. Commit per phase, **explicit paths only** — the pre-existing uncommitted `vendor/`, `composer.*`, `CLAUDE.md`, `.gitignore` changes are left untouched.
6. Every assumption logged in MGE_PROGRESS.md as **ASSUMPTION — confirm**.
