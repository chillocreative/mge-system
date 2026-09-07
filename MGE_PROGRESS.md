# MGE-PMS — Overnight Run Progress Log

**Run started:** 7 September 2026, ~07:15 (+08)
**Operator:** Claude (lead engineer), autonomous — Rahim asleep, authorised to proceed and decide
**Docs:** [MGE_PLAN_EXECUTION.md](MGE_PLAN_EXECUTION.md) · [MGE_AUDIT.md](MGE_AUDIT.md)

> **Rahim — read this file first when you wake.** Section "⚠️ NEEDS YOUR CONFIRMATION" is the short list of things I could not decide for you. Everything else is done or explained.

---

## 🟢 ENGINE ENABLEMENT — Rahim's decisions, 7 Sep 2026

Rahim reviewed the outstanding risks and chose to proceed. Recorded here so the trade-offs are on the record rather than in a chat log.

| Known gap | Effect once the engine is on | Decision |
|---|---|---|
| **Public holidays incomplete** — only 6 fixed-date entries; Deepavali, Maulidur Rasul and Penang state days are missing | Leave spanning a missing holiday **is deducted**. Staff quietly lose days. This is the only gap that costs staff rather than favours them | **Proceed.** HR will inform staff directly |
| **5 of 10 staff have no `hire_date`** | They fall into the lowest tier (14 days). Anyone with real service is under-granted by up to 4 days | **Proceed.** HR fills the dates in later; corrects itself once entered |
| **MC + Hospitalisation now require an attachment** | An employee applying for sick leave without a document is refused outright | Intended (plan AB5). HR to announce |

**What improves immediately:** rest days and public holidays stop being deducted, so staff get days back. On the production snapshot, PTG01 SITI's 17–21 Sep leave drops from 5 days to 3, and her Annual balance goes from 3 to 9. ERLI's goes from 7 to 13.

**Existing approved leave is not recalculated.** Stored `days_count` values stay as they are; the engine applies to new requests only.

**Rollback is one line:** remove `LEAVE_ENGINE_ENABLED` from the production `.env` and redeploy. Because balances are computed live rather than migrated, enabling and disabling are both instant and leave no data to unwind.

---

## ⚠️ NEEDS YOUR CONFIRMATION

Ordered by how much damage a wrong answer causes.

### 1. ✅ RESOLVED — Annual Leave is 14 / 16 / 18

**Rahim confirmed on 7 Sep 2026:** AL is 14 days, tiers 14 / 16 / 18. Applied, flagged as confirmed policy (`is_seed_default = false`), so the settings screen no longer warns about Annual Leave.

This is more generous than the Employment Act minimum of 8/12/16, and consistent with the 14 days `leave_types.default_days_per_year` had been using all along — so seeding the Act figures would have quietly cut junior staff by 6 days.

**Sick Leave confirmed too** (same session): 14 / 18 / 22. The numbers are unchanged — they equal the Act minimum — but they are now a decision somebody made rather than an unexamined fallback, so the flag is cleared and the settings screen no longer warns.

**All entitlement tiers are now confirmed policy.** Nothing in the system is running on a number nobody has looked at.

| Service | Annual | Sick |
|---|---|---|
| < 2 years | 14 | 14 |
| 2–5 years | 16 | 18 |
| 5 years+ | 18 | 22 |

<details><summary>Original conflict, for the record</summary>

### 🔴 Annual Leave: 14 days or 8 days? — **the numbers disagreed**

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

**What I need:** the actual MGE policy.

</details>

### 2. ✅ RESOLVED — production dump received and analysed (7 Sep, 15:21)

Imported to a separate local database (`mge_prod_snapshot`); the dev and production databases were not touched.

**Orphan leave record — cause confirmed.** Leave #2 (2026-06-26, 1.0 day, Annual, approved) belongs to **MGE02 HASLINDA**, soft-deleted on 2026-06-26. Exactly the code-level hypothesis: `employees` soft-deletes, so the FK cascade never fires and the relation resolves to null, rendering as a blank Employee column. **It is a display bug, not data loss** — the record is intact. Go-live blocker #1 of plan 27.2 can be struck off; it is not data repair.

**Shadow run: 69 differences, all understood.**

| Category | Count |
|---|---|
| No stored balance existed yet — not errors | 64 |
| Stored number was wrong, engine right | 4 |
| Already matched | 1 |

The four real ones: MGE01 and PTG01 Annual 8 → 14 (the newly confirmed policy), and TST123 Leman 14 → 8 (joined 22 Jun, so correctly prorated to 7/12 of the year). Proration verified correct across all his leave types.

**Data gap found: 5 of 10 active staff have no `hire_date`** — BM02, MGE01, PTG03, PTG04, PTG05. Without it, service length is unknown, so they land in the lowest tier with no proration. Someone six years in would get 14 days instead of 18, silently.

Rahim's decision: HR fills these in manually. Accepted — it is data entry, not code. To stop it being missed, `leave:recalculate` now **names the affected employees** every run, since that is the command you run before deciding the engine is safe to enable.

**Minor observations — Rahim reviewed all six and decided: keep as they are.** No changes made, and they should not be raised again unless something depends on them.

| Observation | Decision |
|---|---|
| Emergency Leave = 0 days | Keep as is |
| Unpaid Leave = 0 days | Keep as is |
| `leave_types.default_days_per_year` for Annual still 8, disagrees with the confirmed tiers | Keep as is — fallback only, tiers win, no practical impact |
| Maternity 98 · Paternity 7 | Correct per the 2023 amendment, keep |
| 9 of 10 staff have no reporting manager | Keep — does not block, approval routes by leave type |
| TST123 Leman still active | Keep as is |

**Hire dates:** HR will fill these in themselves, since the system is new to them and they are working through staff records anyway. No code change needed — `leave:recalculate` names the affected people every run, so the gap stays visible until it is closed.

<details><summary>Original request, for the record</summary>

### 🔴 Production database dump

I cannot do any of the data work without it. The local DB has **1 employee and 1 leave request** — it is not a copy of production.

Blocked on this:
- confirming the orphan leave record's cause (I have a strong hypothesis — see #3)
- marking test records inactive (`TST123` / Leman Bin Abu, project `test`)
- loading opening balances for Jan–Aug 2026 (plan §27.4 — **mandatory before anyone sees a balance**)
- running the shadow-mode comparison, which is the whole point of stage P3

A sanitised dump (or just `employees`, `leave_requests`, `leave_balances`, `leave_types`) is enough.

</details>

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

### 6. ⚠️ Rebuild the frontend before you deploy

`public/build` is tracked, and `deploy.yml` clears the assets directory then pulls rather than building on the server — so the committed bundle is what production serves. I rebuilt and committed it (`8ca4d7c`), otherwise the deployed app would not contain the new breakdown component.

**But I built against the current working tree, which has uncommitted `package-lock.json` changes that predate this session.** That may not match what `npm ci && npm run build` produces from the committed lockfile. Run a clean rebuild before deploying.

### 7. 2026 public holidays — I deliberately did not guess them

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

### Phase G — Policy administration API ✅ (commit `f8fddea`)

14 endpoints under `/api/leave-policy`: public holidays, work patterns, entitlement tiers, policy settings.

**The holidays endpoints are what HR needs first** — the seeder ships fixed-date holidays only and refuses to guess the lunar ones, so this is where the real 2026 gazette gets entered.

`POST /leave-policy/entitlement-rules/confirm` implements plan 7.3.10(b): the list returns `has_unverified_defaults` so the UI can warn that the tiers are unreviewed statutory minimums, and confirming clears the flag. Editing a tier clears it too — an admin who changed the value has by definition looked at it.

**Permission gates written by hand.** Every write is `leave.manage`: these values decide how many days each employee is owed, and an employee who could edit a tier could grant themselves leave. Reads are wider (`leave.view` / `leave.request`) so the apply form can explain a breakdown without write access. Five tests cover the gates.

Deactivation never hard-deletes — past leave records still reference these rows (plan 26.3).

#### Delegation note
Controller delegated to qwen; review caught four issues before anything ran:
- `settings()` merged a model collection with a `defaults` key, producing a malformed response — genuinely broken
- missing `use App\Http\Controllers\Controller` (fatal on first request)
- `whereYear('date', …)` instead of the denormalised `year` column, bypassing the index added for exactly that query
- `gt:min_years` on a partial update fails when `min_years` is absent from the payload

### Phase H — Day breakdown on the apply form ✅ (commit `4846cf8`)

`POST /api/leaves/preview` plus a live, debounced panel on the apply form:

```
7 calendar days selected
  − Wed 16 Sep · Public holiday (Malaysia Day)
  − Sat 19 Sep · Rest day
  − Sun 20 Sep · Rest day
                                     Deducted   4.0 days
  Annual remaining                             12.0 / 16.0
```

Also surfaces the cross-year split, the shared MC + Hospitalisation cap (so a number that appears to move on its own has a visible reason, plan 7.3.7), and a warning when the request already exceeds the balance — better than a 422 after submitting.

The preview is **server-side**: rest days and holidays are policy data, so deriving them in the browser would risk showing a number the backend disagrees with. With the engine off the endpoint reports `engine_enabled: false` and the panel hides itself.

An employee may only preview their own leave — balances are personal data. Covered by a test.

Frontend builds clean (`npm run build`).

---

## Final state

**104 tests, all passing.** Pint clean. Frontend builds. `php artisan route:list` intact.

**Pushed and deployed on 7 Sep, 14:53 (+08), on Rahim's approval.**

- CI green (backend + frontend), deploy to cPanel succeeded in 32s
- All 9 new migrations ran cleanly on production. The 3 edited migrations did **not** re-run — they were already recorded, exactly as intended
- Production verified healthy afterwards: `/api/health` 200, `/` 200, `POST /api/login` returns 422 on an empty body (validation), not 500

**Production behaviour is unchanged.** `LEAVE_ENGINE_ENABLED` is not set on the production server, so the engine stays off and leave works exactly as it did before. The new tables exist but nothing reads them yet — this is precisely stage P1/P2 of plan 27.14.

### Commits (on `main`, pushed)

| Commit | Phase |
|---|---|
| `888eb75` | docs: audit, execution plan, progress log |
| `edc1fab` | Phase B — policy engine schema (P1) |
| `dd886ca` | Phase C — seed data (P2) |
| `bed59a1` | Phase D — calculation engine |
| `a073246` | Phase E — engine wired in behind a flag |
| `feeef82` | Phase F — shadow mode (P3) |
| `f8fddea` | Phase G — policy administration API |
| `4846cf8` | Phase H — apply form day breakdown |

Pre-existing uncommitted changes (`vendor/`, `composer.*`, `CLAUDE.md`, `.gitignore`, `package-lock.json`) were **left untouched** as instructed.

### To turn it on

```bash
# .env — leave this OFF until the shadow comparison has been reviewed
LEAVE_ENGINE_ENABLED=true
```

Until that line exists, none of this affects anyone.

### What I did not do, and why

| Not done | Why |
|---|---|
| Data cleanup, orphan repair, opening balances | Needs a production dump. Local DB has 1 employee |
| P4 / P5 rollout | Irreversible exposure to real staff — your call |
| Any push or deploy | Explicitly withheld |
| 2026 lunar holiday dates | Would silently corrupt every calculation. HR enters from the gazette |
| Ciri 1–6, 10–25 | Deferred by the plan's own §27 reprioritisation |
| `staff_leave_entitlement_overrides` | Plan §27.3 lists it under "can follow later" (H11) |
| Admin UI screens for policy settings | API is done and tested; the React screens are the natural next session |

---

## Addendum — local login fixed (7 Sep, afternoon)

**Symptom:** `http://mge-system.test` login returned `Session store not set on request.` (HTTP 500).

**Not caused by the overnight run** — none of the 15 commits touch auth, session, middleware or `bootstrap/app.php`. This was config drift from the Herd relink at ~04:36, when the site moved from `mge-pms.test` to `mge-system.test`.

**Root cause:** Sanctum's `EnsureFrontendRequestsAreStateful::fromFrontend()` matches the request's `referer`/`origin` host against `config('sanctum.stateful')`. `mge-system.test` was not in that list, so the session middleware was never applied to the `api` group — and `AuthController@login:34` then called `$request->session()` on a request that had none.

**Fixed in `.env`** (gitignored, local only):

```diff
-SANCTUM_STATEFUL_DOMAINS=mge-pms.test,localhost,…
+SANCTUM_STATEFUL_DOMAINS=mge-system.test,mge-pms.test,localhost,…
-FRONTEND_URL=http://mge-pms.test
+FRONTEND_URL=http://mge-system.test
```

The second one was the same drift: `FRONTEND_URL` is the sole entry in `config/cors.php` `allowed_origins`, and it pointed at `mge-pms.test`, which Herd no longer serves (404 — only `mge-system` and `penangagrotech` are linked).

**Verified:** `/sanctum/csrf-cookie` 204 → `POST /api/login` 200 → `GET /api/user` 200, and a real browser login reaching the Dashboard with data. The single console 401 is the pre-login `/api/user` bootstrap check — expected.

⚠️ **`.env.example` still carries the old domain.** I left it alone because it was already modified before this session and the brief said not to sweep pre-existing changes. Worth updating separately so the next clone does not hit this.

---

## Session 2 — 7 Sep afternoon (engine live + UI build-out)

Leave engine was enabled in production earlier this session. Shipped since, each tested and deploy-verified:

| Feature | State |
|---|---|
| Balance display = enforced figure (bug fix) | ✅ live |
| Employee search combobox (4 screens) + employee numbers | ✅ live |
| Leave balance card on Staff Detail | ✅ live |
| Deploy hardening — pull before clean | ✅ live |
| qwen-agent moved to require-dev (was breaking deploy) | ✅ live |
| **Public Holidays admin screen + national 2026 calendar seeded** | ✅ live |
| **Sidebar accordion (Ciri 12)** | ✅ live |
| **Ciri 9 — status→login propagation, last_working_date, audit** | ✅ live |

133 tests. Public holidays: national only, state excluded by decision; Islamic dates need HR to confirm against the federal warta.

### Honest scope statement — the remaining plan

**Leave block — small, safe, still to do:**
- `staff_leave_entitlement_overrides` (per-employee contract exceptions) — additive
- Opening-balance tool (load Jan–Aug 2026 days already taken) — needs HR's figures
- Ciri 6 — employee_no change audit trail (editing already works; nothing keys on the value)

**The 23 other features are NOT one-session work, and some must not be built without a decision first.** The plan itself (§32) lists five hard-to-reverse decisions. Three of them gate whole modules:

| Decision needed | Blocks |
|---|---|
| Timeline as source of truth (R1) | Entire Correspondence block (Ciri 1/16/17/19) |
| `project_parties` vs three fixed parties (R2) | Correspondence block |
| Recurring events (O5) | Ciri 14 (multi-staff events) |
| Leave privacy (N1) | Ciri 13 (HR Calendar) |

Building these on a live system and pushing them means baking those decisions in irreversibly. I will build every decision-free part, but I will surface these forks rather than guess them — a wrong foundation here is expensive to undo.

**Roughly decision-free, buildable when reached (dependency order):**
1. Shared notification engine (`notification_preferences`, `notification_logs`, throttle/log) — unblocks Ciri 3/11/14
2. Ciri 11 (Memo → notification), Ciri 3 (project-change notification) — once #1 exists
3. Ciri 15 (staff↔project pivot + cards), Ciri 20 (site-log edit window)
4. Shared upload engine → Ciri 4 (Drawings folder), Ciri 5 (BQ import)
5. Assets block (Ciri 21–24), Ciri 2 (machinery report), Safety block (Ciri 25)

---

## Session 2 continued — Batches 1–5 shipped (7 Sep evening)

All live on production, each pushed + CI + deploy verified. **182 tests.**

| Batch | Features |
|---|---|
| 1 | Shared notification engine (preferences, logs, email gate, throttle) |
| 2 | Per-staff entitlement overrides · opening-balance import (`leave:opening-balance`) · employee-no audit |
| 3 | Staff project-involvement card (Ciri 15) · site-log edit window (Ciri 20) · Penang holiday removed |
| 4 | Shared upload engine · Drawings bulk/folder upload (Ciri 4) · BQ template + importer (Ciri 5) |
| 5 | Project-change notification (Ciri 3) · memo→notification category (Ciri 11) |
| — | Security: fixed an IDOR in the contract drawing endpoints (found by commit review) |

Plus earlier this session: Leave engine live, holidays admin, sidebar accordion (Ciri 12), Ciri 9 status→login, balance-display fix, employee search, staff leave card, deploy hardening.

### Remaining plan features (not yet built)

**Decision-free — buildable when reached:**
- Ciri 21–24 Assets block (machinery→assets sync, serial/chassis no, pencil icon, project assignment)
- Ciri 2 machinery monthly report (endpoint C8 exists; report needs building)
- Ciri 25 Safety block (large)
- Ciri 13 HR Calendar aggregate · Ciri 14 multi-staff events (Ciri 14 carries the recurring-event decision O5; 13 carries the leave-privacy decision N1)

**Gated on an irreversible decision (must be settled first):**
- Ciri 1+16+17+19 Correspondence block — needs "timeline as source of truth" (R1) and `project_parties` vs three fixed parties (R2) decided before building (plan §32)
- Ciri 18 Correspondence PDF — follows the Correspondence block

### Still open for HR/ops (not code)
- Run `leave:opening-balance` with HR's Jan–Aug figures
- Fill `hire_date` for the 5 staff missing it
- Confirm Islamic 2026 holiday dates against the federal warta
- Email channel stays off until SMTP verified (then flip `NOTIFICATIONS_EMAIL_ENABLED`)

---

## Batch 6 progress (7 Sep, late)

| Sub-batch | Feature | State |
|---|---|---|
| 6a | Ciri 2 — days-based machinery usage report | ✅ live |
| 6b | Ciri 22/23/24 — vehicle serial numbers, project assignment (history), pencil icon | ✅ live |

**190 tests. All pushed, CI + deploy verified, prod healthy.**

### Remaining — each needs a decision I won't guess on a live system

- **Ciri 21** (machinery→asset sync): needs the machinery-master modeling decided — vehicles-of-type-machinery, inventory, or a new table; and whether site logs auto-create assets.
- **Ciri 13** (HR Calendar aggregate): 🔴 carries the **leave-privacy decision (N1)** — one of the plan's five hard-to-reverse choices. Showing who is on what leave (esp. MC) to the wrong audience is a real privacy harm that is hard to walk back, so I will not default it. Need: who sees leave on the shared calendar — HR + self only, or names visible to all, or an anonymised "unavailable"?
- **Ciri 14** (multi-staff events): needs the **recurring-events decision (O5)**. The multi-staff pivot + notifications are decision-free and I can build those; recurrence is the fork.
- **Ciri 25** (Safety block): plan-sized "Besar", 10 sub-features (Z1–Z12) with their own sub-decisions (PTW approval flow, man-hours source, toolbox-vs-safety-meeting overlap). A multi-batch effort in itself.
- **Correspondence block (Ciri 1/16/17/19 + 18)**: you said proceed; I'll build it additively with the plan's recommended defaults for R1 (timeline as source of truth) and R2 (project_parties) logged as assumptions — but it is the largest single block and reshapes a live module, so it warrants being its own focused session.
