# MGE-PMS — Overnight Run Progress Log

**Run started:** 7 September 2026, ~07:15 (+08)
**Operator:** Claude (lead engineer), autonomous — Rahim asleep, authorised to proceed and decide
**Docs:** [MGE_PLAN_EXECUTION.md](MGE_PLAN_EXECUTION.md) · [MGE_AUDIT.md](MGE_AUDIT.md)

> **Rahim — read this file first when you wake.** Section "⚠️ NEEDS YOUR CONFIRMATION" is the short list of things I could not decide for you. Everything else is done or explained.

---

## ⚠️ NEEDS YOUR CONFIRMATION

Ordered by how much damage a wrong answer causes.

### 1. 🔴 Annual Leave: 14 days or 8 days? — **the numbers currently disagree**

The live system already seeds `leave_types.default_days_per_year`:

| Type | Currently seeded in the live schema |
|---|---|
| Annual (AL) | **14** |
| Sick (MC) | **14** |
| Hospitalisation (HL) | 60 |

The plan (§7.3.9) tells me to seed Employment Act 1955 minimums instead:

| Service | AL | MC |
|---|---|---|
| < 2 years | **8** | 14 |
| 2–5 years | 12 | 18 |
| 5+ years | 16 | 22 |

**These are not compatible.** A junior employee is on 14 days today and would drop to **8** under the Act minimum. If I seed the Act values and they go live, staff lose 6 days of AL and HR gets an inbox full of complaints — this is precisely the silent-policy failure the plan warns about at §7.3.10.

**What I did:** seeded the Act tiers as specified, but flagged **every row `is_seed_default = true`**, and the engine is behind a config flag that is **off**. Nothing is live. Nobody's balance changed.

**What I need:** the actual MGE policy. My read is that 14 flat is closer to your real policy than the Act minimum, and the Act tiers were the plan author's placeholder. If MGE genuinely gives 14/16/18 or similar, tell me the tiers and I will re-seed in minutes.

### 2. 🔴 Production database dump

I cannot do any of the data work without it. The local DB has **1 employee and 1 leave request** — it is not a copy of production.

Blocked on this:
- confirming the orphan leave record's cause (I have a strong hypothesis — see #3)
- marking test records inactive (`TST123` / Leman Bin Abu, project `test`)
- loading opening balances for Jan–Aug 2026 (plan §27.4 — **mandatory before anyone sees a balance**)
- running the shadow-mode comparison, which is the whole point of stage P3

A sanitised dump (or just `employees`, `leave_requests`, `leave_balances`, `leave_types`) is enough.

### 3. Orphan leave record (plan 0.3 #13) — I think I found the cause, from code alone

`employees` uses `softDeletes()`, but `leave_requests.employee_id` was declared `cascadeOnDelete`. A soft delete is an `UPDATE`, so the DB cascade never fires — the leave rows survive, and their employee relation resolves to `null`. That renders exactly as **a leave row with a blank Employee column**.

Confirm on production:

```sql
SELECT lr.id, lr.employee_id, lr.start_date, lr.days_count, lr.status,
       e.employee_no, e.deleted_at
FROM leave_requests lr
LEFT JOIN employees e ON e.id = lr.employee_id
WHERE e.id IS NULL OR e.deleted_at IS NOT NULL;
```

If `deleted_at` is set → confirmed, and this is a **display bug, not data loss**. The record is fine; it just needs to resolve trashed employees. That answers AB18 and downgrades §27.2 blocker #1 from "data repair" to "one scope fix".

### 4. Leave approval is routed by leave *type*, not by reporting manager

The plan assumes `Reporting Manager → Director` and lists empty Reporting Manager fields as a go-live blocker (§27.2 #2). **The code doesn't work that way.** Approval is configured per leave type (`leave_types.manager_approver_id` / `director_approver_id`), with `users.is_manager` / `is_director` as a fallback. `employees.reporting_manager_id` exists but the leave flow ignores it completely.

So requests do **not** get stuck today. But it means everyone's leave goes to the same approver(s) regardless of who they report to.

**Which do you want?** Keep type-based routing (simpler, working now), or switch to per-employee reporting manager (matches the plan, needs the field filled for all staff)? I did not change this — it is a live approval path and the answer is a policy decision, not a technical one.

### 5. Site staff rest days — fixed or rotating? (plan AB15)

I built work patterns as **per-category with a per-employee override** (plan's recommended option B): Office = Mon–Fri, Site = Mon–Sat resting Sunday. If site crews actually rotate their rest day week to week, option B under-counts and we need option C (per-employee schedule). The override FK is already in place so C is an addition later, not a rewrite.

### 6. 2026 public holidays — I deliberately did not guess them

Fixed-date holidays are seeded. Chinese New Year, Hari Raya Aidilfitri/Aidiladha, Deepavali, Wesak, Awal Muharram, Maulidur Rasul, Thaipusam and the Agong's birthday are lunar/gazetted and move every year. Inventing dates would silently mis-deduct leave for every employee. **HR must enter them from the official gazette** — the admin screen for that is Phase G.

---

## ASSUMPTIONS — confirm

| # | Decision I made | Why | Reversible? |
|---|---|---|---|
| A1 | Build `leave_days` (one row per leave day, plan §27.13, question AB20 unanswered) | Plan strongly recommends it and it collapses four separate edge cases into one structure | Yes — additive table |
| A2 | Keep `leave_balances.used_days` / `remaining_days` names; add new columns alongside | Renaming live columns breaks working code for zero benefit | n/a |
| A3 | Tier boundary `min_years ≤ service < max_years`, implemented in exactly one place | Plan §7.3.8 fixes this; matches how the Act is worded | Yes |
| A4 | Quota pool defaults to Mode B (MC + Hospitalisation share a 60-day cap) | Plan §7.3.9; matches the Act's framing | Yes — admin-tunable |
| A5 | Engine ships behind a config flag, default **off** | System is live. Nothing changes until you switch it on | Yes |
| A6 | Leave module only; all other 24 features deferred | Plan's own §27 reprioritises Leave to first | Yes |
| A7 | Malay plan → English code and docs | Matches existing repo conventions | n/a |

---

## Phase log

### Phase 0 — Verify plan file ✅
- `stat` twice, ~5 min apart: 287,692 bytes, mtime 2026-09-07 06:56:38, **unchanged** → file is finished, not being written.
- Read in full. Structure intact: 32 sections, clean ending, no truncation.
- Confirmed it is a **planning document** (v0.2, "Planning sahaja — TIADA coding lagi"), largely composed of specs plus open questions. Roughly a third of its decision tables are still unanswered — that is by design, not damage.

### Phase A — Code audit ✅
Output: [MGE_AUDIT.md](MGE_AUDIT.md).

Environment verified working: PHP 8.4.23, Laravel 12.53, MySQL 9.7 reachable (`mge_pms`), Node 22 + node_modules present, baseline `php artisan test` passes (2 example tests).

**The plan overestimates the remaining work**, because it was written from screenshots without code access:

| Plan says | Reality |
|---|---|
| `project_members` may not exist — **listed as a top-8 blocker**, gates Ciri 3/14/15/20 | ✅ **Exists.** Blocker already cleared |
| Ciri 9 employment status is a new build | Mostly exists — `employees.status` is already `active/inactive/resigned` + soft deletes |
| Approval flow to build | Two-level manager→director flow already built |
| Notification infra to build | `notifications` table + `NotificationService` + `LeaveStatusNotification` all exist |
| Queue still on `sync` | Already `database` |
| Frontend is Blade/Livewire/Inertia/Filament | ❌ React 19 SPA + Vite 7 + Tailwind 4 |

**And underestimates one thing:** there is **no test coverage at all** — 2 example tests. The plan's §27.7 edge-case list is effectively a test plan for an engine with nothing protecting it. Hence: engine built test-first.

**Six tables** turn out to be the entire P1 schema scope, not the ~fifteen the plan implies.

**The core problem, located.** `app/Services/LeaveService.php:apply()`:

```php
$days = $halfDay ? 0.5 : ($start->diffInDays($end) + 1);
```

Raw calendar days. No rest days, no public holidays, no work pattern, no cross-year split, **no balance check of any kind** — an employee can request 100 days of AL and nothing stops them. `requires_attachment` is declared on `leave_types` and never enforced. Replacing this correctly is the whole job.

### Phase B — P1 schema ✅ (commit `edc1fab`)

Six new tables + three column additions. **Additive only** — nothing reads them yet, so this is the plan's 🟢 near-zero-risk stage on a live system.

| Table | Purpose |
|---|---|
| `work_patterns` | per-category rest-day patterns (plan 27.6b) |
| `public_holidays` | national + state, per year |
| `leave_policy_settings` | admin-tunable, global or per leave type, versioned via `effective_from` |
| `leave_entitlement_rules` | service-year tiers, `min ≤ service < max`, with `is_seed_default` flag |
| `leave_quota_pools` | combined caps — MC + Hospitalisation sharing 60 days |
| `leave_days` | one row per leave day (plan 27.13) |

Extended, **never renamed** (these columns are live): `leave_balances` += `carried_forward`, `adjustment_days`, `rule_snapshot`, `calculated_at`, `is_locked`; `leave_types` += `quota_pool_id`; `employees` += `work_pattern_id`.

**Verified:** `migrate` → `rollback` → `migrate` round-trip clean on MySQL; column positions confirmed correct in the live schema; 12 tests pass (10 new).

#### 🔧 Side fix — the test suite could never run

Three pre-existing migrations used raw MySQL `ALTER TABLE ... MODIFY`, which sqlite cannot parse. `RefreshDatabase` therefore died at migration **14 of 92**, meaning **no feature test in this repo has ever been able to run**. That is why there were only 2 example tests.

Made them driver-aware (`2026_02_13_000001`, `2026_08_06_000003`, `2026_08_06_000006`). The MySQL branch is byte-identical to before — production behaviour is unchanged, and these migrations have already run there so they will not re-execute. Only sqlite takes the new path.

This was not optional: Phase D's correctness depends entirely on tests, and tests could not run.

#### Delegation note
Migrations and models delegated to `qwen-agent`; I reviewed and fixed before applying. Two real bugs caught in review:
- `->constrained()->nullOnDelete()->after(...)` — `after()` chained onto the `ForeignKeyDefinition` instead of the column, so it would not have positioned the column. Reordered.
- `dropForeign()` + `dropColumn()` replaced with the repo's `dropConstrainedForeignId()` convention.

Redundant `protected $table` declarations stripped from the six models to match existing conventions. The driver-aware migration fixes I wrote directly — schema-risky work is not delegated.

### Phase C — Seed data ✅ (commit `dd886ca`)

Work patterns (Office Mon–Fri, Site Mon–Sat), the MC + Hospitalisation 60-day shared pool, 13 global policy defaults, Employment Act entitlement tiers (all flagged `is_seed_default`), and fixed-date public holidays.

**Verified:** seeded twice on both MySQL and sqlite — row counts unchanged. 20 tests pass.

**The idempotency test caught a real bug**, which is exactly why it was written. `firstOrCreate` on a cast `date` column stores `2026-01-01 00:00:00` but looks up `2026-01-01`, so the second run missed the existing row and violated the unique index. That is the "re-run reverts HR's tuning" failure mode of plan 7.3.10(c). Replaced with a `whereDate` lookup that behaves correctly on both drivers.

**Also confirmed:** `leave_types` are seeded **inside the migration itself**, so `default_days_per_year` AL = 14 is already in production. This is the source of the conflict in item 1 above.

**Deliberately omitted:** all lunar/gazetted holidays. See item 6.

### Phase D — Calculation engine ✅ (commit `bed59a1`) ⭐

The core of the module. Written by Claude directly, test-first — not delegated, per the run brief (entitlement logic is money logic).

| Class | Responsibility |
|---|---|
| `LeaveDayCalculator` | days in range − rest days − public holidays (plan 27.6b), one row per day |
| `CalculatedLeave` | result object: total, per-year split, exclusions with reasons |
| `EntitlementResolver` | service tiers, tier crossing, proration, rounding |
| `LeavePolicy` | settings resolution (type override → global → hardcoded default) + `rule_snapshot` |
| `WorkPatternResolver` | which weekdays an employee actually works |
| `HolidayCalendar` | national + Penang holidays, other states ignored |

**39 new tests**, one per edge case in plan §27.7. **59 tests pass overall.** Pint clean.

#### Design decisions worth knowing

**Safe degradation.** Every fallback errs towards being visible rather than silent:

| Missing config | Behaviour | Why |
|---|---|---|
| No work pattern | every day counts | Over-deduction gets reported. A silent under-deduction is found at year end, after the leave was taken |
| No matching tier | `leave_types.default_days_per_year` | Never zero (plan 7.3.3a) |
| No hire date | zero service | Never silently award the top tier |
| Unknown rounding mode | `nearest_half` | Never throw mid-balance-calculation |

**Tier boundary in exactly one method.** `min_years ≤ service < max_years` lives only in `EntitlementResolver::resolveTier()`. Plan 7.3.8 warns that an inconsistent boundary gives staff at exactly 2.00 or 5.00 years different answers on different screens — untraceable once it arrives as an HR complaint. There is a test pinning 1.999999 → lower tier and 2.000000 → upper.

**Rest day beats public holiday.** When a holiday falls on someone's rest day, the recorded reason is `rest_day` — they were not working anyway, so it must not be counted as a bonus day off.

**Nothing is wired up yet.** `config('leave.engine_enabled')` defaults to `false`. Leave behaves today exactly as it did yesterday.

#### ⚠️ Two smaller assumptions logged
- `new_joiner_proration` values `full` and `none` are treated identically (no proration). The plan lists both without distinguishing them. If `none` was meant to mean "no leave at all in the first year", tell me — one-line change.
- `staff_leave_entitlement_overrides` (plan 7.2, per-employee contract exceptions) **not built** — plan §27.3 lists it under "boleh menyusul" (can follow later, question H11).

### Phase E — Engine wired in, behind a flag ✅ (commit `a073246`)

`config('leave.engine_enabled')`, default **false**. Two tests pin the flag-off path to exactly what production does today — including the unflattering one documenting that a 100-day annual leave request is currently accepted without complaint.

With the flag on, `apply()` now:
- computes days via the engine (rest days + public holidays excluded)
- writes one `leave_days` row per calendar day, excluded ones included with their reason
- **enforces `requires_attachment`** — declared on `leave_types` since June, never actually checked until now
- **refuses a request that exceeds the balance**, checked *per leave year* so a New Year-spanning request is measured against both
- **counts pending requests**, so two overlapping requests cannot both be approved off the same remaining days (plan 8.4)

`approve()` derives balances from `leave_days` rather than incrementing a counter. `reject()` and `cancel()` drop the day rows and the balance recovers on its own (plan 27.13).

#### Two decisions worth knowing

**Legacy requests still count.** Production rows have `days_count` but no `leave_days`. Without a fallback, every employee would appear to have their full entitlement back the moment the engine was switched on — a silent, system-wide over-grant. Covered by a test.

**Hospitalisation shows the effective balance, not the raw cap** — 56 once 4 days of MC are taken, not 60. Plan 7.3.7: showing 60 means the employee requests 60, is refused at 56, and the complaint goes to HR rather than being self-explanatory.

### Phase F — Shadow mode ✅ (commit `feeef82`) ⭐

```bash
php artisan leave:recalculate --year=2026
```

Calculates every employee's balance and reports where it differs from what is stored. **Writes nothing** unless `--commit`. Locked years are skipped and reported separately.

Plan 27.14 calls this the most valuable stage of the go-live and I agree — it validates the engine against *every* employee on real data while the current numbers stay in use, replacing the manual 10-employee spot check of plan 27.6 with an automatic check across everybody, at zero risk.

**This is the command to run first against a production dump.** Verified working end-to-end on the local database.

---

## Where this leaves the go-live

| Plan stage | State |
|---|---|
| P1 — new tables only | ✅ Done, verified reversible |
| P2 — seed data | ✅ Done, idempotent |
| P3 — shadow mode | ✅ **Tool built and tested.** Needs a production dump to run for real |
| P4 — balances visible to HR only | Needs Phase G admin UI + your sign-off |
| P5 — open to all staff | 🔴 Your decision. Point of no return |

**79 tests, all passing.** Zero behaviour change in production: the engine is off, and every commit is local — nothing pushed, nothing deployed.

### Suggested order when you wake

1. Answer item 1 (the AL 8-vs-14 conflict) — it blocks everything downstream
2. Send a production dump, or run the orphan-record SQL in item 3 yourself
3. Run `leave:recalculate --year=2026` against the dump and read the differences
4. Decide item 4 (approval routing) — it is a policy call, not a technical one
