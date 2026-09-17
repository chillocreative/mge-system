# Monthly Progress Report — Phase A (Data Foundation) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add every piece of project data the Monthly Progress Report needs (contract particulars, parties, org chart, progress periods, baseline, claim columns, correspondence groups, delay notices, tests, tender-drawing flag, per-project site-log categories, report images) with API + a "Report Data" tab, so Phase B can generate the report from real data.

**Architecture:** Each dataset is a small Laravel model + migration + thin controller under `App\Http\Controllers\Api\ReportData\`, all nested under `/api/projects/{project}/…` and protected by the existing `projects.view` / `projects.edit` permissions with the same "resolve through the project" IDOR guard the codebase already uses. The frontend adds one `Report Data` tab to `ProjectDetail.jsx` whose panels live in new files under `resources/js/pages/projects/report-data/`. No report generation in this phase.

**Tech Stack:** Laravel 12 (PHP 8.2+), MySQL, PHPUnit, Spatie permissions; React 19 + React Router 7 + Tailwind 4 + Axios (`apiClient`), Vite. No new Composer packages in Phase A.

**Spec:** `docs/superpowers/specs/2026-09-17-monthly-progress-report-design.md` (§3.1–3.13, §6, §7 item 4, §11)

## Global Constraints

- Run Pint as `php vendor/bin/pint` (exec bit is not set); tests as `php artisan test tests/Feature/ReportData` (or `php -d memory_limit=2G vendor/bin/phpunit` for the full suite).
- Validation rules for uploads use `extensions:` never `mimes:`; never MIME-sniff (production PHP has no `fileinfo`). Inline file responses set `Content-Type` from an extension map.
- Any `resources/js/**` change is incomplete until `npm run build` runs and `public/build/manifest.json` + the new hashed assets are committed (production never runs Vite).
- Controllers use `$this->success()` / `$this->created()` from `App\Traits\ApiResponse`, inline `$request->validate([...])`, and `NormalizesNullableColumns::dropNullColumns` for NOT NULL columns with defaults.
- Nested resources are always loaded through the project: `Model::where('project_id', $projectId)->findOrFail($id)`.
- Frontend API calls go through `resources/js/services/*Service.js` using `apiClient`; toasts via `react-hot-toast`; confirmations via `useConfirm()` from `@/context/ConfirmContext`; download/view links use `rel="noopener"` (never `noreferrer`).
- Commit after every task with the attribution line `Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>`.

---

## File structure

**Backend (create)**
- `database/migrations/2026_09_18_000001_add_report_cutoff_day_to_projects.php`
- `database/migrations/2026_09_18_000002_add_contract_particulars_to_project_contracts.php`
- `database/migrations/2026_09_18_000003_add_report_fields_to_project_parties.php`
- `database/migrations/2026_09_18_000004_add_org_chart_fields_to_project_members.php`
- `database/migrations/2026_09_18_000005_create_project_progress_tables.php`
- `database/migrations/2026_09_18_000006_add_report_columns_to_project_invoices.php`
- `database/migrations/2026_09_18_000007_add_report_group_to_correspondence_types.php`
- `database/migrations/2026_09_18_000008_create_project_delay_notices_table.php`
- `database/migrations/2026_09_18_000009_create_project_tests_table.php`
- `database/migrations/2026_09_18_000010_add_is_tender_to_drawings.php`
- `database/migrations/2026_09_18_000011_create_project_resource_categories_table.php`
- `database/migrations/2026_09_18_000012_create_report_images_table.php`
- `app/Models/ProjectPartyContact.php`, `ProjectScheduleBaseline.php`, `ProjectProgressPeriod.php`, `ProjectDelayNotice.php`, `ProjectTest.php`, `ProjectResourceCategory.php`, `ReportImage.php`
- `app/Support/RinggitWords.php` — amount in words
- `app/Support/ReportPeriod.php` — cutoff → period dates
- `app/Services/ReportData/ProgressService.php` — variance/ahead-delay maths
- `app/Services/ReportData/ResourceCategoryService.php` — defaults + validation list
- `app/Http/Controllers/Api/ReportData/ContractParticularsController.php`, `ReportPartyController.php`, `OrgChartController.php`, `ProgressPeriodController.php`, `ScheduleBaselineController.php`, `DelayNoticeController.php`, `ProjectTestController.php`, `ResourceCategoryController.php`, `ReportImageController.php`
- `tests/Feature/ReportData/*Test.php` (one per task) and `tests/Unit/RinggitWordsTest.php`, `tests/Unit/ReportPeriodTest.php`

**Backend (modify)**
- `app/Models/Project.php`, `ProjectContract.php`, `ProjectMember.php`, `ProjectInvoice.php`, `Drawing.php`, `CorrespondenceType.php` (fillable/casts/relations)
- `app/Http/Controllers/Api/ContractController.php` (`is_main` handling), `ProjectInvoiceController.php` (new columns), `DrawingController.php` (`is_tender`), `SiteLogController.php` (category validation)
- `routes/api.php`

**Frontend (create)**
- `resources/js/services/reportDataService.js`
- `resources/js/pages/projects/report-data/ReportDataTab.jsx` (panel switcher)
- `resources/js/pages/projects/report-data/ContractParticularsPanel.jsx`
- `resources/js/pages/projects/report-data/PartiesPanel.jsx`
- `resources/js/pages/projects/report-data/OrgChartPanel.jsx`
- `resources/js/pages/projects/report-data/ProgressPanel.jsx`
- `resources/js/pages/projects/report-data/RegistersPanel.jsx` (delay notices + tests)
- `resources/js/pages/projects/report-data/ResourceCategoriesPanel.jsx`
- `resources/js/pages/projects/report-data/ReportImagesPanel.jsx`

**Frontend (modify)**
- `resources/js/pages/projects/ProjectDetail.jsx` (tab entry only)
- `resources/js/pages/projects/contracts/ContractDetail.jsx` (Particulars card + "main contract" toggle)

---

### Task 1: Project cutoff day + `ReportPeriod` helper

**Files:**
- Create: `database/migrations/2026_09_18_000001_add_report_cutoff_day_to_projects.php`
- Create: `app/Support/ReportPeriod.php`
- Modify: `app/Models/Project.php` (fillable), `app/Http/Controllers/Api/ProjectController.php` (validation), `app/Http/Requests/*Project*` if project validation lives there — grep `report_cutoff_day` target: wherever `location` is validated for projects
- Test: `tests/Unit/ReportPeriodTest.php`, `tests/Feature/ReportData/ProjectCutoffDayTest.php`

**Interfaces:**
- Produces: `App\Support\ReportPeriod::forMonth(int $cutoffDay, int $year, int $month): array{start: Carbon, end: Carbon}` and `ReportPeriod::containing(int $cutoffDay, Carbon $date): array{start, end}`; `projects.report_cutoff_day` (tinyint, default 15).

- [ ] **Step 1: Write the failing unit test**

```php
<?php

namespace Tests\Unit;

use App\Support\ReportPeriod;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ReportPeriodTest extends TestCase
{
    public function test_period_for_month_runs_from_day_after_cutoff_of_previous_month_to_cutoff(): void
    {
        $p = ReportPeriod::forMonth(15, 2026, 1);

        $this->assertSame('2025-12-16', $p['start']->toDateString());
        $this->assertSame('2026-01-15', $p['end']->toDateString());
    }

    public function test_cutoff_31_clamps_to_month_length(): void
    {
        $p = ReportPeriod::forMonth(31, 2026, 2);

        $this->assertSame('2026-02-01', $p['start']->toDateString());
        $this->assertSame('2026-02-28', $p['end']->toDateString());
    }

    public function test_containing_returns_the_period_a_date_falls_in(): void
    {
        $p = ReportPeriod::containing(15, Carbon::parse('2026-01-20'));

        $this->assertSame('2026-01-16', $p['start']->toDateString());
        $this->assertSame('2026-02-15', $p['end']->toDateString());
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/ReportPeriodTest.php`
Expected: FAIL — `Class "App\Support\ReportPeriod" not found`

- [ ] **Step 3: Implement the helper**

```php
<?php

namespace App\Support;

use Carbon\Carbon;

class ReportPeriod
{
    /** @return array{start: Carbon, end: Carbon} */
    public static function forMonth(int $cutoffDay, int $year, int $month): array
    {
        $end = Carbon::create($year, $month, 1)->endOfMonth();
        $end = $end->copy()->day(min($cutoffDay, $end->day))->startOfDay();

        $prevMonthEnd = Carbon::create($year, $month, 1)->subMonth()->endOfMonth();
        $start = $prevMonthEnd->copy()->day(min($cutoffDay, $prevMonthEnd->day))->addDay()->startOfDay();

        return ['start' => $start, 'end' => $end];
    }

    /** @return array{start: Carbon, end: Carbon} */
    public static function containing(int $cutoffDay, Carbon $date): array
    {
        $candidate = self::forMonth($cutoffDay, $date->year, $date->month);
        if ($date->gt($candidate['end'])) {
            $next = $date->copy()->addMonthNoOverflow();
            $candidate = self::forMonth($cutoffDay, $next->year, $next->month);
        }

        return $candidate;
    }
}
```

- [ ] **Step 4: Run the unit test**

Run: `php vendor/bin/phpunit tests/Unit/ReportPeriodTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Migration + model + validation**

Migration:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedTinyInteger('report_cutoff_day')->default(15)->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('report_cutoff_day');
        });
    }
};
```

Add `'report_cutoff_day'` to `Project::$fillable`. Where project create/update is validated (grep `'location' =>` in `app/Http/Requests` and `ProjectController`), add `'report_cutoff_day' => ['nullable', 'integer', 'min:1', 'max:28']` next to it (max 28 so every month has the day; UI explains).

- [ ] **Step 6: Feature test**

```php
<?php

namespace Tests\Feature\ReportData;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectCutoffDayTest extends TestCase
{
    use RefreshDatabase;

    public function test_cutoff_day_defaults_to_15_and_can_be_updated(): void
    {
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $u = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->givePermissionTo(['projects.view', 'projects.edit']);
        $project = Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);

        $this->assertSame(15, $project->fresh()->report_cutoff_day);

        $this->actingAs($u)->putJson("/api/projects/{$project->id}", ['report_cutoff_day' => 25])->assertOk();
        $this->assertSame(25, $project->fresh()->report_cutoff_day);

        $this->actingAs($u)->putJson("/api/projects/{$project->id}", ['report_cutoff_day' => 31])->assertStatus(422);
    }
}
```

- [ ] **Step 7: Migrate, run, lint, commit**

Run: `php artisan migrate && php artisan test tests/Feature/ReportData/ProjectCutoffDayTest.php && php vendor/bin/pint --test`
Expected: migration DONE, 1 test PASS, Pint pass.

```bash
git add database/migrations/2026_09_18_000001_add_report_cutoff_day_to_projects.php app/Support/ReportPeriod.php app/Models/Project.php app/Http tests/Unit/ReportPeriodTest.php tests/Feature/ReportData/ProjectCutoffDayTest.php
git commit -m "Add per-project report cutoff day and ReportPeriod helper"
```

---

### Task 2: Contract particulars + main contract + `RinggitWords`

**Files:**
- Create: `database/migrations/2026_09_18_000002_add_contract_particulars_to_project_contracts.php`, `app/Support/RinggitWords.php`, `app/Http/Controllers/Api/ReportData/ContractParticularsController.php`
- Modify: `app/Models/ProjectContract.php`, `app/Services/ContractService.php`, `app/Http/Controllers/Api/ContractController.php`, `routes/api.php`
- Test: `tests/Unit/RinggitWordsTest.php`, `tests/Feature/ReportData/ContractParticularsTest.php`

**Interfaces:**
- Produces: `RinggitWords::spell(float|string $amount): string`; `ProjectContract` columns listed below plus `is_main`; `GET /api/projects/{project}/contract-particulars` → `{contract: {...}, contract_sum_words: string}`; `PUT` same path updates the main contract's particulars; `ContractService::setMain(int $projectId, int $contractId)`.

- [ ] **Step 1: Unit test for amount in words**

```php
<?php

namespace Tests\Unit;

use App\Support\RinggitWords;
use PHPUnit\Framework\TestCase;

class RinggitWordsTest extends TestCase
{
    public function test_whole_ringgit(): void
    {
        $this->assertSame('Two Hundred Eighty-Eight Million Ringgit Only', RinggitWords::spell(288000000));
        $this->assertSame('Fourteen Million Four Hundred Thousand Ringgit Only', RinggitWords::spell('14400000.00'));
        $this->assertSame('One Ringgit Only', RinggitWords::spell(1));
    }

    public function test_with_sen(): void
    {
        $this->assertSame('Fifty-Two Thousand One Hundred Twenty-Eight Ringgit And Fifty Sen Only', RinggitWords::spell(52128.50));
    }

    public function test_zero(): void
    {
        $this->assertSame('Zero Ringgit Only', RinggitWords::spell(0));
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/RinggitWordsTest.php` — Expected: class not found.

- [ ] **Step 3: Implement**

```php
<?php

namespace App\Support;

class RinggitWords
{
    private const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    private const SCALES = [1000000000 => 'Billion', 1000000 => 'Million', 1000 => 'Thousand'];

    public static function spell(float|string $amount): string
    {
        $cents = (int) round(((float) $amount) * 100);
        $ringgit = intdiv($cents, 100);
        $sen = $cents % 100;

        $words = $ringgit === 0 ? 'Zero' : self::number($ringgit);
        $result = $words.' Ringgit';
        if ($sen > 0) {
            $result .= ' And '.self::number($sen).' Sen';
        }

        return $result.' Only';
    }

    private static function number(int $n): string
    {
        $parts = [];
        foreach (self::SCALES as $value => $label) {
            if ($n >= $value) {
                $parts[] = self::number(intdiv($n, $value)).' '.$label;
                $n %= $value;
            }
        }
        if ($n >= 100) {
            $parts[] = self::ONES[intdiv($n, 100)].' Hundred';
            $n %= 100;
        }
        if ($n >= 20) {
            $tens = self::TENS[intdiv($n, 10)];
            $parts[] = $n % 10 ? $tens.'-'.self::ONES[$n % 10] : $tens;
        } elseif ($n > 0) {
            $parts[] = self::ONES[$n];
        }

        return implode(' ', $parts);
    }
}
```

- [ ] **Step 4: Run unit test** — Expected: PASS (3 tests).

- [ ] **Step 5: Migration + model**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_contracts', function (Blueprint $table) {
            $table->boolean('is_main')->default(false)->after('status');
            $table->decimal('contract_sum', 15, 2)->nullable();
            $table->decimal('performance_bond_amount', 15, 2)->nullable();
            $table->unsignedSmallInteger('duration_months')->nullable();
            $table->unsignedSmallInteger('dlp_months')->nullable();
            $table->decimal('lad_per_day', 12, 2)->nullable();
            $table->date('possession_date')->nullable();
            $table->date('completion_date')->nullable();
            $table->date('dlp_start_date')->nullable();
            $table->date('dlp_end_date')->nullable();
            $table->string('cidb_registration')->nullable();
            $table->json('insurances')->nullable();
            $table->index(['project_id', 'is_main']);
        });
    }

    public function down(): void
    {
        Schema::table('project_contracts', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'is_main']);
            $table->dropColumn(['is_main', 'contract_sum', 'performance_bond_amount', 'duration_months', 'dlp_months', 'lad_per_day', 'possession_date', 'completion_date', 'dlp_start_date', 'dlp_end_date', 'cidb_registration', 'insurances']);
        });
    }
};
```

In `ProjectContract`: add all 12 columns to `$fillable`; add casts `'is_main' => 'boolean', 'insurances' => 'array', 'possession_date' => 'date:Y-m-d', 'completion_date' => 'date:Y-m-d', 'dlp_start_date' => 'date:Y-m-d', 'dlp_end_date' => 'date:Y-m-d'`.

- [ ] **Step 6: Feature test**

```php
<?php

namespace Tests\Feature\ReportData;

use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ContractParticularsTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->editor = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $this->editor->givePermissionTo(['projects.view', 'projects.edit']);
    }

    private function project(): Project
    {
        return Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
    }

    public function test_setting_a_contract_as_main_unsets_the_previous_one(): void
    {
        $project = $this->project();
        $c1 = ProjectContract::create(['project_id' => $project->id, 'title' => 'C1', 'is_main' => true]);
        $c2 = ProjectContract::create(['project_id' => $project->id, 'title' => 'C2']);

        $this->actingAs($this->editor)->putJson("/api/project-contracts/{$c2->id}", ['is_main' => true])->assertOk();

        $this->assertFalse($c1->fresh()->is_main);
        $this->assertTrue($c2->fresh()->is_main);
    }

    public function test_particulars_are_read_and_written_on_the_main_contract(): void
    {
        $project = $this->project();
        ProjectContract::create(['project_id' => $project->id, 'title' => 'Main', 'is_main' => true]);

        $this->actingAs($this->editor)->putJson("/api/projects/{$project->id}/contract-particulars", [
            'contract_sum' => 288000000,
            'performance_bond_amount' => 14400000,
            'duration_months' => 24,
            'dlp_months' => 12,
            'lad_per_day' => 52128,
            'possession_date' => '2025-10-31',
            'completion_date' => '2027-10-31',
            'cidb_registration' => 'TBA',
            'insurances' => [['type' => "Contractor's All Risk", 'insurer' => 'PACIFIC INSURANCE', 'policy_no' => 'CEC-E0039188-H1', 'period_from' => '2025-10-31', 'period_to' => '2027-10-31', 'maintenance_from' => '2027-11-01', 'maintenance_to' => '2029-02-12']],
        ])->assertOk();

        $res = $this->actingAs($this->editor)->getJson("/api/projects/{$project->id}/contract-particulars")->assertOk();
        $this->assertSame('Two Hundred Eighty-Eight Million Ringgit Only', $res->json('data.contract_sum_words'));
        $this->assertSame('PACIFIC INSURANCE', $res->json('data.contract.insurances.0.insurer'));
    }

    public function test_returns_404_when_project_has_no_main_contract(): void
    {
        $project = $this->project();

        $this->actingAs($this->editor)->getJson("/api/projects/{$project->id}/contract-particulars")->assertNotFound();
    }
}
```

- [ ] **Step 7: Run it to verify it fails** — Expected: 404 on the PUT routes (route missing) / `is_main` not applied.

- [ ] **Step 8: Service + controllers + routes**

`ContractService` — add:
```php
public function setMain(int $projectId, int $contractId): void
{
    \Illuminate\Support\Facades\DB::transaction(function () use ($projectId, $contractId) {
        ProjectContract::where('project_id', $projectId)->where('id', '!=', $contractId)->update(['is_main' => false]);
        ProjectContract::where('project_id', $projectId)->findOrFail($contractId)->update(['is_main' => true]);
    });
}

public function mainContract(int $projectId): ProjectContract
{
    return ProjectContract::where('project_id', $projectId)->where('is_main', true)->firstOrFail();
}
```

`ContractController::update()` — add `'is_main' => ['sometimes', 'boolean']` to `validatePayload()`; after the existing update, if `$request->boolean('is_main')` then `$this->contractService->setMain($contract->project_id, $contract->id)`. (Setting `false` just writes the column.)

New controller:
```php
<?php

namespace App\Http\Controllers\Api\ReportData;

use App\Http\Controllers\Controller;
use App\Services\ContractService;
use App\Support\RinggitWords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractParticularsController extends Controller
{
    public function __construct(private ContractService $contracts) {}

    public function show(int $projectId): JsonResponse
    {
        $contract = $this->contracts->mainContract($projectId);

        return $this->success([
            'contract' => $contract,
            'contract_sum_words' => $contract->contract_sum !== null ? RinggitWords::spell($contract->contract_sum) : null,
            'performance_bond_words' => $contract->performance_bond_amount !== null ? RinggitWords::spell($contract->performance_bond_amount) : null,
        ]);
    }

    public function update(int $projectId, Request $request): JsonResponse
    {
        $contract = $this->contracts->mainContract($projectId);

        $validated = $request->validate([
            'contract_sum' => ['nullable', 'numeric', 'min:0'],
            'performance_bond_amount' => ['nullable', 'numeric', 'min:0'],
            'duration_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'dlp_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'lad_per_day' => ['nullable', 'numeric', 'min:0'],
            'possession_date' => ['nullable', 'date'],
            'completion_date' => ['nullable', 'date'],
            'dlp_start_date' => ['nullable', 'date'],
            'dlp_end_date' => ['nullable', 'date'],
            'cidb_registration' => ['nullable', 'string', 'max:255'],
            'insurances' => ['nullable', 'array', 'max:10'],
            'insurances.*.type' => ['required', 'string', 'max:255'],
            'insurances.*.insurer' => ['nullable', 'string', 'max:255'],
            'insurances.*.policy_no' => ['nullable', 'string', 'max:255'],
            'insurances.*.period_from' => ['nullable', 'date'],
            'insurances.*.period_to' => ['nullable', 'date'],
            'insurances.*.maintenance_from' => ['nullable', 'date'],
            'insurances.*.maintenance_to' => ['nullable', 'date'],
        ]);

        $contract->update($validated);

        return $this->show($projectId);
    }
}
```

Routes — inside the existing `Route::prefix('projects')` area, next to `{project}/milestones`:
```php
Route::prefix('{project}/contract-particulars')->middleware('permission:projects.view')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\ReportData\ContractParticularsController::class, 'show']);
    Route::put('/', [\App\Http\Controllers\Api\ReportData\ContractParticularsController::class, 'update'])->middleware('permission:projects.edit');
});
```
(Import the class with `use` at the top of `routes/api.php` like the others.)

- [ ] **Step 9: Migrate, test, lint, commit**

Run: `php artisan migrate && php artisan test tests/Feature/ReportData/ContractParticularsTest.php tests/Feature/Projects && php vendor/bin/pint --test`
Expected: all PASS (existing contract tests unaffected).

```bash
git add database/migrations/2026_09_18_000002_add_contract_particulars_to_project_contracts.php app/Support/RinggitWords.php app/Models/ProjectContract.php app/Services/ContractService.php app/Http/Controllers/Api/ContractController.php app/Http/Controllers/Api/ReportData/ContractParticularsController.php routes/api.php tests/Unit/RinggitWordsTest.php tests/Feature/ReportData/ContractParticularsTest.php
git commit -m "Add contract particulars, main-contract flag and amount-in-words helper"
```

---

### Task 3: Report fields on project parties + party contacts (with logo)

> **Revised during execution (ruling):** `project_parties` already exists (migration
> `2026_09_07_270001_add_correspondence_workflow.php`, model `App\Models\ProjectParty`,
> routes `/api/project-parties`, used by Correspondence). We EXTEND it instead of creating a
> second parties table, so Correspondence and the report share one party list. Existing
> columns: `name` (company), `type` (client|consultant|main_contractor|subcontractor|supplier|
> authority|other), `contact_person`, `email`, `phone`, `is_active`. The existing controller,
> routes and `correspondenceService.js` functions stay untouched.

**Files:**
- Create: `database/migrations/2026_09_18_000003_add_report_fields_to_project_parties.php`, `app/Models/ProjectPartyContact.php`, `app/Http/Controllers/Api/ReportData/ReportPartyController.php`
- Modify: `app/Models/ProjectParty.php`, `app/Models/Project.php` (`parties()` relation), `routes/api.php`
- Test: `tests/Feature/ReportData/ReportPartyTest.php`

**Interfaces:**
- Produces: `project_parties` gains `report_role` (string 40, nullable), `role_label` (nullable), `address` (text nullable), `logo_path` (nullable), `sort_order` (smallint default 0); `ProjectParty::REPORT_ROLES = ['owner','superintending_officer','so_representative','district_engineer','quantity_surveyor','consultant','contractor','other']`; `ProjectParty::contacts()` hasMany `ProjectPartyContact {project_party_id, name, designation, phone, email, sort_order}`; routes `GET/POST /api/projects/{project}/parties`, `PUT/DELETE /api/projects/{project}/parties/{party}`, `POST …/{party}/logo` (multipart `logo`), `GET …/{party}/logo` (inline image). Contacts are replaced wholesale via `contacts: [...]` in the party payload. `Project::parties()` → `hasMany(ProjectParty::class)->orderBy('sort_order')`.
- Later tasks (report builder, Task 10 panel) read `name` as the company name, `report_role` for the report row, and `contacts`.

- [ ] **Step 1: Failing test**

```php
<?php

namespace Tests\Feature\ReportData;

use App\Models\Project;
use App\Models\ProjectParty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportPartyTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->editor = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $this->editor->givePermissionTo(['projects.view', 'projects.edit']);
    }

    private function project(): Project
    {
        return Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
    }

    public function test_party_with_report_role_and_contacts_is_created_and_contacts_are_replaced_on_update(): void
    {
        $project = $this->project();

        $res = $this->actingAs($this->editor)->postJson("/api/projects/{$project->id}/parties", [
            'name' => 'Jurutera Perunding Riz Sdn. Bhd.',
            'type' => 'consultant',
            'report_role' => 'consultant',
            'address' => '73-2, Petaling Utama Avenue',
            'contacts' => [
                ['name' => 'Ir. Khairul Izman Bin Kamal', 'phone' => '+60 12-222 8429'],
                ['name' => 'Ir. Muhammad Hazwan bin Hamzah', 'designation' => 'Resident Engineer'],
            ],
        ])->assertCreated();
        $id = $res->json('data.id');
        $this->assertCount(2, $res->json('data.contacts'));
        $this->assertSame('consultant', ProjectParty::find($id)->report_role);

        $this->actingAs($this->editor)->putJson("/api/projects/{$project->id}/parties/{$id}", [
            'contacts' => [['name' => 'Only One']],
        ])->assertOk();
        $this->assertSame(1, ProjectParty::find($id)->contacts()->count());
    }

    public function test_existing_correspondence_parties_still_work_without_report_fields(): void
    {
        $project = $this->project();
        $party = ProjectParty::create(['project_id' => $project->id, 'name' => 'JPS', 'type' => 'client']);

        $this->assertNull($party->fresh()->report_role);
        $list = $this->actingAs($this->editor)->getJson("/api/projects/{$project->id}/parties")->assertOk();
        $this->assertSame('JPS', $list->json('data.0.name'));
        $this->assertSame([], $list->json('data.0.contacts'));
    }

    public function test_role_other_requires_a_label_and_parties_are_scoped_to_project(): void
    {
        $p1 = $this->project();
        $p2 = $this->project();
        $this->actingAs($this->editor)->postJson("/api/projects/{$p1->id}/parties", ['name' => 'X', 'report_role' => 'other'])->assertStatus(422);
        $party = ProjectParty::create(['project_id' => $p1->id, 'name' => 'JPS', 'type' => 'client']);

        $this->actingAs($this->editor)->deleteJson("/api/projects/{$p2->id}/parties/{$party->id}")->assertNotFound();
    }

    public function test_logo_upload_and_inline_view(): void
    {
        Storage::fake('local');
        $project = $this->project();
        $party = ProjectParty::create(['project_id' => $project->id, 'name' => 'MGE', 'type' => 'main_contractor']);

        $this->actingAs($this->editor)->post("/api/projects/{$project->id}/parties/{$party->id}/logo", [
            'logo' => UploadedFile::fake()->create('mge.png', 20, 'image/png'),
        ])->assertOk();

        $this->assertNotNull($party->fresh()->logo_path);
        Storage::disk('local')->assertExists($party->fresh()->logo_path);

        $res = $this->actingAs($this->editor)->get("/api/projects/{$project->id}/parties/{$party->id}/logo");
        $res->assertOk();
        $this->assertSame('image/png', $res->headers->get('content-type'));
    }
}
```

- [ ] **Step 2: Run** — Expected: FAIL (404 routes / unknown columns).

- [ ] **Step 3: Migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_parties', function (Blueprint $table) {
            $table->string('report_role', 40)->nullable()->after('type');
            $table->string('role_label')->nullable()->after('report_role');
            $table->text('address')->nullable()->after('phone');
            $table->string('logo_path')->nullable()->after('address');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('logo_path');
        });

        Schema::create('project_party_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_party_id')->constrained('project_parties')->cascadeOnDelete();
            $table->string('name');
            $table->string('designation')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_party_contacts');
        Schema::table('project_parties', function (Blueprint $table) {
            $table->dropColumn(['report_role', 'role_label', 'address', 'logo_path', 'sort_order']);
        });
    }
};
```

- [ ] **Step 4: Models**

`ProjectParty` (extend, keep everything that exists):
```php
public const REPORT_ROLES = ['owner', 'superintending_officer', 'so_representative', 'district_engineer', 'quantity_surveyor', 'consultant', 'contractor', 'other'];

protected $fillable = ['project_id', 'name', 'type', 'contact_person', 'email', 'phone', 'is_active', 'report_role', 'role_label', 'address', 'logo_path', 'sort_order'];

public function contacts(): HasMany
{
    return $this->hasMany(ProjectPartyContact::class)->orderBy('sort_order');
}
```

`ProjectPartyContact`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPartyContact extends Model
{
    protected $fillable = ['project_party_id', 'name', 'designation', 'phone', 'email', 'sort_order'];

    public function party(): BelongsTo
    {
        return $this->belongsTo(ProjectParty::class, 'project_party_id');
    }
}
```

`Project::parties()` → `return $this->hasMany(ProjectParty::class)->orderBy('sort_order');` (add only if no `parties()` relation exists yet; if one exists, keep it and add the ordering).

- [ ] **Step 5: Controller `ReportPartyController`**

```php
<?php

namespace App\Http\Controllers\Api\ReportData;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectParty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportPartyController extends Controller
{
    private const LOGO_TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];

    private const PARTY_TYPES = 'client,consultant,main_contractor,subcontractor,supplier,authority,other';

    public function index(int $projectId): JsonResponse
    {
        return $this->success(ProjectParty::where('project_id', $projectId)->with('contacts')->orderBy('sort_order')->orderBy('id')->get());
    }

    public function store(int $projectId, Request $request): JsonResponse
    {
        Project::findOrFail($projectId);
        $validated = $this->validatePayload($request, true);
        $contacts = $validated['contacts'] ?? [];
        unset($validated['contacts']);
        $validated['project_id'] = $projectId;

        $party = DB::transaction(function () use ($validated, $contacts) {
            $party = ProjectParty::create($validated);
            $this->syncContacts($party, $contacts);

            return $party;
        });

        return $this->created($party->load('contacts'), 'Party added.');
    }

    public function update(int $projectId, int $partyId, Request $request): JsonResponse
    {
        $party = ProjectParty::where('project_id', $projectId)->findOrFail($partyId);
        $validated = $this->validatePayload($request, false);
        $contacts = array_key_exists('contacts', $validated) ? $validated['contacts'] : null;
        unset($validated['contacts']);

        DB::transaction(function () use ($party, $validated, $contacts) {
            $party->update($validated);
            if ($contacts !== null) {
                $this->syncContacts($party, $contacts);
            }
        });

        return $this->success($party->fresh()->load('contacts'), 'Party updated.');
    }

    public function destroy(int $projectId, int $partyId): JsonResponse
    {
        $party = ProjectParty::where('project_id', $projectId)->findOrFail($partyId);
        if ($party->logo_path) {
            Storage::disk('local')->delete($party->logo_path);
        }
        $party->delete();

        return $this->success(null, 'Party removed.');
    }

    public function storeLogo(int $projectId, int $partyId, Request $request): JsonResponse
    {
        $party = ProjectParty::where('project_id', $projectId)->findOrFail($partyId);
        $request->validate(['logo' => ['required', 'file', 'max:2048', 'extensions:png,jpg,jpeg,webp']]);

        if ($party->logo_path) {
            Storage::disk('local')->delete($party->logo_path);
        }
        $party->update(['logo_path' => $request->file('logo')->store("projects/{$projectId}/party-logos", 'local')]);

        return $this->success($party->fresh()->load('contacts'), 'Logo uploaded.');
    }

    public function showLogo(int $projectId, int $partyId)
    {
        $party = ProjectParty::where('project_id', $projectId)->findOrFail($partyId);
        abort_unless($party->logo_path && Storage::disk('local')->exists($party->logo_path), 404);

        $ext = strtolower(pathinfo($party->logo_path, PATHINFO_EXTENSION));

        return response()->file(Storage::disk('local')->path($party->logo_path), [
            'Content-Type' => self::LOGO_TYPES[$ext] ?? 'application/octet-stream',
            'Content-Disposition' => 'inline',
        ]);
    }

    private function validatePayload(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'type' => ['nullable', 'in:'.self::PARTY_TYPES],
            'report_role' => ['nullable', 'in:'.implode(',', ProjectParty::REPORT_ROLES)],
            'role_label' => ['nullable', 'string', 'max:100', 'required_if:report_role,other'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'contacts' => ['sometimes', 'array', 'max:20'],
            'contacts.*.name' => ['required', 'string', 'max:255'],
            'contacts.*.designation' => ['nullable', 'string', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:50'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
        ]);
    }

    private function syncContacts(ProjectParty $party, array $contacts): void
    {
        $party->contacts()->delete();
        foreach (array_values($contacts) as $i => $c) {
            $party->contacts()->create($c + ['sort_order' => $i]);
        }
    }
}
```
Note `type` defaults to `other` at DB level when omitted, so `type` is nullable here; the existing `ProjectPartyController` keeps its own stricter rules.

Routes (nested project group, with `use App\Http\Controllers\Api\ReportData\ReportPartyController;`):
```php
Route::prefix('{project}/parties')->middleware('permission:projects.view')->group(function () {
    Route::get('/', [ReportPartyController::class, 'index']);
    Route::post('/', [ReportPartyController::class, 'store'])->middleware('permission:projects.edit');
    Route::put('/{party}', [ReportPartyController::class, 'update'])->middleware('permission:projects.edit');
    Route::delete('/{party}', [ReportPartyController::class, 'destroy'])->middleware('permission:projects.edit');
    Route::post('/{party}/logo', [ReportPartyController::class, 'storeLogo'])->middleware('permission:projects.edit');
    Route::get('/{party}/logo', [ReportPartyController::class, 'showLogo']);
});
```

- [ ] **Step 6: Migrate, test, lint, commit**

Run: `php artisan migrate && php artisan test tests/Feature/ReportData/ReportPartyTest.php tests/Feature/Correspondence && php vendor/bin/pint --test` — Expected: 4 PASS and the existing correspondence suite still green.

```bash
git add database/migrations/2026_09_18_000003_add_report_fields_to_project_parties.php app/Models/ProjectParty.php app/Models/ProjectPartyContact.php app/Models/Project.php app/Http/Controllers/Api/ReportData/ReportPartyController.php routes/api.php tests/Feature/ReportData/ReportPartyTest.php
git commit -m "Extend project parties with report role, address, logo and contacts"
```

---

### Task 4: Org chart fields on project members

**Files:**
- Create: `database/migrations/2026_09_18_000004_add_org_chart_fields_to_project_members.php`, `app/Http/Controllers/Api/ReportData/OrgChartController.php`
- Modify: `app/Models/ProjectMember.php`, `routes/api.php`
- Test: `tests/Feature/ReportData/OrgChartTest.php`

**Interfaces:**
- Produces: `project_members.designation`, `reports_to_user_id`, `org_sort`; `GET /api/projects/{project}/org-chart` → `{data: [ {user_id, name, designation, reports_to_user_id, org_sort, children: [...] } ]}` (forest rooted at members with null `reports_to_user_id`); `PUT /api/projects/{project}/org-chart` with `members: [{user_id, designation, reports_to_user_id, org_sort}]` (bulk update of existing members only).

- [ ] **Step 1: Failing test**

```php
<?php

namespace Tests\Feature\ReportData;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OrgChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_chart_is_saved_and_returned_as_a_tree(): void
    {
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $editor = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $editor->givePermissionTo(['projects.view', 'projects.edit']);
        $pm = User::create(['first_name' => 'Norazlinda', 'last_name' => 'Sabarudin', 'email' => 'pm-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $se = User::create(['first_name' => 'Ahmad', 'last_name' => 'Haziq', 'email' => 'se-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $project = Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
        $project->members()->attach([$pm->id => ['role' => 'member'], $se->id => ['role' => 'member']]);

        $this->actingAs($editor)->putJson("/api/projects/{$project->id}/org-chart", ['members' => [
            ['user_id' => $pm->id, 'designation' => 'Project Manager', 'reports_to_user_id' => null, 'org_sort' => 0],
            ['user_id' => $se->id, 'designation' => 'Senior Engineer', 'reports_to_user_id' => $pm->id, 'org_sort' => 0],
        ]])->assertOk();

        $res = $this->actingAs($editor)->getJson("/api/projects/{$project->id}/org-chart")->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('Project Manager', $res->json('data.0.designation'));
        $this->assertSame('Senior Engineer', $res->json('data.0.children.0.designation'));
    }
}
```

- [ ] **Step 2: Run** — Expected: FAIL (404).

- [ ] **Step 3: Migration, model, controller, route**

Migration:
```php
Schema::table('project_members', function (Blueprint $table) {
    $table->string('designation')->nullable()->after('role');
    $table->foreignId('reports_to_user_id')->nullable()->after('designation')->constrained('users')->nullOnDelete();
    $table->unsignedSmallInteger('org_sort')->default(0)->after('reports_to_user_id');
});
```
(`down()` drops the foreign key then the three columns.)

`ProjectMember::$fillable` += `designation`, `reports_to_user_id`, `org_sort`. In `Project::members()` add `->withPivot('role', 'joined_at', 'left_at', 'designation', 'reports_to_user_id', 'org_sort')`.

Controller:
```php
<?php

namespace App\Http\Controllers\Api\ReportData;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrgChartController extends Controller
{
    public function show(int $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);
        $rows = $project->members()->whereNull('project_members.left_at')->get()->map(fn ($u) => [
            'user_id' => $u->id,
            'name' => trim($u->first_name.' '.$u->last_name),
            'designation' => $u->pivot->designation,
            'reports_to_user_id' => $u->pivot->reports_to_user_id,
            'org_sort' => (int) $u->pivot->org_sort,
        ])->sortBy('org_sort')->values();

        return $this->success($this->tree($rows, null));
    }

    public function update(int $projectId, Request $request): JsonResponse
    {
        $project = Project::findOrFail($projectId);
        $memberIds = $project->members()->pluck('users.id')->all();

        $validated = $request->validate([
            'members' => ['required', 'array'],
            'members.*.user_id' => ['required', 'integer', 'in:'.implode(',', $memberIds ?: [0])],
            'members.*.designation' => ['nullable', 'string', 'max:255'],
            'members.*.reports_to_user_id' => ['nullable', 'integer', 'different:members.*.user_id', 'in:'.implode(',', $memberIds ?: [0])],
            'members.*.org_sort' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($project, $validated) {
            foreach ($validated['members'] as $m) {
                $project->members()->updateExistingPivot($m['user_id'], [
                    'designation' => $m['designation'] ?? null,
                    'reports_to_user_id' => $m['reports_to_user_id'] ?? null,
                    'org_sort' => $m['org_sort'] ?? 0,
                ]);
            }
        });

        return $this->show($projectId);
    }

    private function tree($rows, ?int $parentId): array
    {
        return $rows->where('reports_to_user_id', $parentId)->map(fn ($r) => $r + ['children' => $this->tree($rows, $r['user_id'])])->values()->all();
    }
}
```

Routes:
```php
Route::prefix('{project}/org-chart')->middleware('permission:projects.view')->group(function () {
    Route::get('/', [OrgChartController::class, 'show']);
    Route::put('/', [OrgChartController::class, 'update'])->middleware('permission:projects.edit');
});
```

- [ ] **Step 4: Migrate, test, lint, commit**

Run: `php artisan migrate && php artisan test tests/Feature/ReportData/OrgChartTest.php tests/Feature/Projects/StaffProjectInvolvementTest.php && php vendor/bin/pint --test` — Expected: PASS.

```bash
git add database/migrations/2026_09_18_000004_add_org_chart_fields_to_project_members.php app/Models/ProjectMember.php app/Models/Project.php app/Http/Controllers/Api/ReportData/OrgChartController.php routes/api.php tests/Feature/ReportData/OrgChartTest.php
git commit -m "Add designation and reporting line to project members for the org chart"
```

---

### Task 5: Schedule baseline + progress periods with computed variance

**Files:**
- Create: `database/migrations/2026_09_18_000005_create_project_progress_tables.php`, `app/Models/ProjectScheduleBaseline.php`, `app/Models/ProjectProgressPeriod.php`, `app/Services/ReportData/ProgressService.php`, `app/Http/Controllers/Api/ReportData/ScheduleBaselineController.php`, `app/Http/Controllers/Api/ReportData/ProgressPeriodController.php`
- Modify: `routes/api.php`
- Test: `tests/Unit/ProgressServiceTest.php`, `tests/Feature/ReportData/ProgressPeriodTest.php`

**Interfaces:**
- Produces: `ProjectScheduleBaseline {project_id, month(date, first day), scheduled_physical_pct, scheduled_financial_amount, scheduled_financial_pct, source}`; `ProjectProgressPeriod {project_id, period_no, period_start, period_end, planning_days_completion, physical_scheduled_pct, physical_actual_pct, financial_scheduled_pct, financial_actual_pct, financial_actual_amount, ahead_delay_days, physical_status, financial_status, notes}` plus computed `physical_variance`, `financial_variance` (appended attributes);
  `ProgressService::compute(array $period): array` fills `physical_variance`, `financial_variance`, `ahead_delay_days` (when null), `physical_status` (when null);
  `ProgressService::scheduledFor(int $projectId, Carbon $periodEnd): ?ProjectScheduleBaseline`;
  routes `GET /api/projects/{project}/schedule-baseline`, `PUT` (bulk replace `rows: [{month:'2026-01', scheduled_physical_pct, scheduled_financial_amount, scheduled_financial_pct}]`); `GET/POST /api/projects/{project}/progress-periods`, `PUT/DELETE /{period}`, `GET /api/projects/{project}/progress-periods/suggest?period_end=YYYY-MM-DD` → defaults (next period_no, dates from cutoff, scheduled values from baseline, financial actual from claims).

- [ ] **Step 1: Unit test for the maths**

```php
<?php

namespace Tests\Unit;

use App\Services\ReportData\ProgressService;
use PHPUnit\Framework\TestCase;

class ProgressServiceTest extends TestCase
{
    public function test_variance_and_ahead_days_are_derived(): void
    {
        $out = (new ProgressService)->compute([
            'planning_days_completion' => 497,
            'physical_scheduled_pct' => 2,
            'physical_actual_pct' => 4,
            'financial_scheduled_pct' => 15,
            'financial_actual_pct' => 13,
            'ahead_delay_days' => null,
            'physical_status' => null,
        ]);

        $this->assertSame(2.0, $out['physical_variance']);
        $this->assertSame(-2.0, $out['financial_variance']);
        $this->assertSame(10, $out['ahead_delay_days']);   // round(2/100 * 497)
        $this->assertSame('AHEAD', $out['physical_status']);
    }

    public function test_status_on_track_when_variance_zero_and_overrides_are_kept(): void
    {
        $out = (new ProgressService)->compute([
            'planning_days_completion' => 497,
            'physical_scheduled_pct' => 1,
            'physical_actual_pct' => 1,
            'financial_scheduled_pct' => 13,
            'financial_actual_pct' => 0,
            'ahead_delay_days' => 3,
            'physical_status' => 'DELAY',
        ]);

        $this->assertSame(0.0, $out['physical_variance']);
        $this->assertSame(3, $out['ahead_delay_days']);
        $this->assertSame('DELAY', $out['physical_status']);
    }
}
```

- [ ] **Step 2: Run** — Expected: class not found.

- [ ] **Step 3: Service**

```php
<?php

namespace App\Services\ReportData;

use App\Models\ProjectInvoice;
use App\Models\ProjectScheduleBaseline;
use Carbon\Carbon;

class ProgressService
{
    public function compute(array $p): array
    {
        $p['physical_variance'] = round((float) ($p['physical_actual_pct'] ?? 0) - (float) ($p['physical_scheduled_pct'] ?? 0), 2);
        $p['financial_variance'] = round((float) ($p['financial_actual_pct'] ?? 0) - (float) ($p['financial_scheduled_pct'] ?? 0), 2);

        if (($p['ahead_delay_days'] ?? null) === null) {
            $days = (int) ($p['planning_days_completion'] ?? 0);
            $p['ahead_delay_days'] = (int) round($p['physical_variance'] / 100 * $days);
        }

        if (empty($p['physical_status'])) {
            $p['physical_status'] = match (true) {
                $p['physical_variance'] > 0 => 'AHEAD',
                $p['physical_variance'] < 0 => 'DELAY',
                default => 'ON TRACK',
            };
        }

        return $p;
    }

    public function scheduledFor(int $projectId, Carbon $periodEnd): ?ProjectScheduleBaseline
    {
        return ProjectScheduleBaseline::where('project_id', $projectId)
            ->where('month', $periodEnd->copy()->startOfMonth()->toDateString())
            ->first();
    }

    /** Sum of certified claim amounts up to the period end, as a % of contract sum. */
    public function financialActual(int $projectId, Carbon $periodEnd, ?float $contractSum): array
    {
        $amount = (float) ProjectInvoice::where('project_id', $projectId)
            ->where('type', 'client')
            ->whereNotNull('payment_cert_date')
            ->where('payment_cert_date', '<=', $periodEnd->toDateString())
            ->sum('amount');
        $pct = $contractSum ? round($amount / $contractSum * 100, 2) : 0.0;

        return ['amount' => $amount, 'pct' => $pct];
    }
}
```
(`project_invoices.type` is `client` for claims to the client and `subcon` for subcontractor invoices — verified in `ProjectInvoiceController`.)

- [ ] **Step 4: Run unit test** — Expected: PASS.

- [ ] **Step 5: Migration + models**

```php
Schema::create('project_schedule_baselines', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->date('month');
    $table->decimal('scheduled_physical_pct', 5, 2)->default(0);
    $table->decimal('scheduled_financial_amount', 15, 2)->nullable();
    $table->decimal('scheduled_financial_pct', 5, 2)->nullable();
    $table->string('source', 20)->default('manual');
    $table->timestamps();
    $table->unique(['project_id', 'month']);
});

Schema::create('project_progress_periods', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->unsignedSmallInteger('period_no');
    $table->date('period_start');
    $table->date('period_end');
    $table->unsignedInteger('planning_days_completion')->nullable();
    $table->decimal('physical_scheduled_pct', 5, 2)->default(0);
    $table->decimal('physical_actual_pct', 5, 2)->default(0);
    $table->decimal('financial_scheduled_pct', 5, 2)->default(0);
    $table->decimal('financial_actual_pct', 5, 2)->default(0);
    $table->decimal('financial_actual_amount', 15, 2)->nullable();
    $table->integer('ahead_delay_days')->nullable();
    $table->string('physical_status', 20)->nullable();
    $table->string('financial_status', 50)->nullable();
    $table->text('notes')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->unique(['project_id', 'period_no']);
});
```

Models: fillable = all columns above; casts dates `'date:Y-m-d'`, decimals `'decimal:2'`. `ProjectProgressPeriod` adds
```php
protected $appends = ['physical_variance', 'financial_variance'];

public function getPhysicalVarianceAttribute(): float
{
    return round((float) $this->physical_actual_pct - (float) $this->physical_scheduled_pct, 2);
}

public function getFinancialVarianceAttribute(): float
{
    return round((float) $this->financial_actual_pct - (float) $this->financial_scheduled_pct, 2);
}
```

- [ ] **Step 6: Feature test**

```php
<?php

namespace Tests\Feature\ReportData;

use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\ProjectScheduleBaseline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProgressPeriodTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->editor = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $this->editor->givePermissionTo(['projects.view', 'projects.edit']);
    }

    private function project(): Project
    {
        return Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress', 'report_cutoff_day' => 15]);
    }

    public function test_baseline_bulk_replace(): void
    {
        $project = $this->project();
        $this->actingAs($this->editor)->putJson("/api/projects/{$project->id}/schedule-baseline", ['rows' => [
            ['month' => '2025-12', 'scheduled_physical_pct' => 1, 'scheduled_financial_amount' => 37440000, 'scheduled_financial_pct' => 13],
            ['month' => '2026-01', 'scheduled_physical_pct' => 2, 'scheduled_financial_amount' => 43200000, 'scheduled_financial_pct' => 15],
        ]])->assertOk();
        $this->assertSame(2, ProjectScheduleBaseline::where('project_id', $project->id)->count());
        $this->assertSame('2026-01-01', ProjectScheduleBaseline::where('project_id', $project->id)->orderByDesc('month')->first()->month->toDateString());
    }

    public function test_suggest_fills_period_dates_and_scheduled_values_from_baseline(): void
    {
        $project = $this->project();
        ProjectContract::create(['project_id' => $project->id, 'title' => 'Main', 'is_main' => true, 'contract_sum' => 288000000]);
        ProjectScheduleBaseline::create(['project_id' => $project->id, 'month' => '2026-01-01', 'scheduled_physical_pct' => 2, 'scheduled_financial_pct' => 15]);

        $res = $this->actingAs($this->editor)->getJson("/api/projects/{$project->id}/progress-periods/suggest?period_end=2026-01-15")->assertOk();
        $this->assertSame(1, $res->json('data.period_no'));
        $this->assertSame('2025-12-16', $res->json('data.period_start'));
        $this->assertSame('2026-01-15', $res->json('data.period_end'));
        $this->assertEquals(2, $res->json('data.physical_scheduled_pct'));
        $this->assertEquals(15, $res->json('data.financial_scheduled_pct'));
    }

    public function test_period_is_stored_with_computed_variance_and_status(): void
    {
        $project = $this->project();
        $res = $this->actingAs($this->editor)->postJson("/api/projects/{$project->id}/progress-periods", [
            'period_no' => 3, 'period_start' => '2025-12-16', 'period_end' => '2026-01-15',
            'planning_days_completion' => 497,
            'physical_scheduled_pct' => 2, 'physical_actual_pct' => 4,
            'financial_scheduled_pct' => 15, 'financial_actual_pct' => 13, 'financial_status' => 'IPC No. 3',
        ])->assertCreated();

        $this->assertEquals(2, $res->json('data.physical_variance'));
        $this->assertSame(10, $res->json('data.ahead_delay_days'));
        $this->assertSame('AHEAD', $res->json('data.physical_status'));

        $this->actingAs($this->editor)->postJson("/api/projects/{$project->id}/progress-periods", [
            'period_no' => 3, 'period_start' => '2026-01-16', 'period_end' => '2026-02-15',
        ])->assertStatus(422); // duplicate period_no
    }
}
```

- [ ] **Step 7: Controllers + routes**

`ScheduleBaselineController`:
```php
public function index(int $projectId): JsonResponse
{
    return $this->success(ProjectScheduleBaseline::where('project_id', $projectId)->orderBy('month')->get());
}

public function replace(int $projectId, Request $request): JsonResponse
{
    Project::findOrFail($projectId);
    $validated = $request->validate([
        'rows' => ['present', 'array', 'max:120'],
        'rows.*.month' => ['required', 'date_format:Y-m'],
        'rows.*.scheduled_physical_pct' => ['required', 'numeric', 'min:0', 'max:100'],
        'rows.*.scheduled_financial_amount' => ['nullable', 'numeric', 'min:0'],
        'rows.*.scheduled_financial_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
    ]);

    DB::transaction(function () use ($projectId, $validated) {
        ProjectScheduleBaseline::where('project_id', $projectId)->delete();
        foreach ($validated['rows'] as $r) {
            ProjectScheduleBaseline::create([
                'project_id' => $projectId,
                'month' => $r['month'].'-01',
                'scheduled_physical_pct' => $r['scheduled_physical_pct'],
                'scheduled_financial_amount' => $r['scheduled_financial_amount'] ?? null,
                'scheduled_financial_pct' => $r['scheduled_financial_pct'] ?? null,
                'source' => 'manual',
            ]);
        }
    });

    return $this->index($projectId);
}
```

`ProgressPeriodController` (constructor injects `ProgressService $progress`):
```php
public function index(int $projectId): JsonResponse
{
    return $this->success(ProjectProgressPeriod::where('project_id', $projectId)->orderBy('period_no')->get());
}

public function suggest(int $projectId, Request $request): JsonResponse
{
    $project = Project::findOrFail($projectId);
    $request->validate(['period_end' => ['required', 'date']]);
    $end = Carbon::parse($request->period_end);
    $period = ReportPeriod::containing((int) $project->report_cutoff_day, $end);
    $baseline = $this->progress->scheduledFor($projectId, $period['end']);
    $contract = ProjectContract::where('project_id', $projectId)->where('is_main', true)->first();
    $fin = $this->progress->financialActual($projectId, $period['end'], $contract?->contract_sum !== null ? (float) $contract->contract_sum : null);
    $last = ProjectProgressPeriod::where('project_id', $projectId)->max('period_no');

    return $this->success([
        'period_no' => (int) $last + 1,
        'period_start' => $period['start']->toDateString(),
        'period_end' => $period['end']->toDateString(),
        'physical_scheduled_pct' => $baseline?->scheduled_physical_pct !== null ? (float) $baseline->scheduled_physical_pct : null,
        'financial_scheduled_pct' => $baseline?->scheduled_financial_pct !== null ? (float) $baseline->scheduled_financial_pct : null,
        'financial_actual_amount' => $fin['amount'],
        'financial_actual_pct' => $fin['pct'],
        'planning_days_completion' => $contract?->possession_date && $contract?->completion_date
            ? Carbon::parse($contract->possession_date)->diffInDays(Carbon::parse($contract->completion_date)) : null,
    ]);
}

public function store(int $projectId, Request $request): JsonResponse
{
    Project::findOrFail($projectId);
    $validated = $this->validatePayload($request, $projectId, null);
    $validated = $this->progress->compute($validated);
    unset($validated['physical_variance'], $validated['financial_variance']);
    $validated['project_id'] = $projectId;
    $validated['created_by'] = $request->user()->id;

    return $this->created(ProjectProgressPeriod::create($validated), 'Progress period saved.');
}

public function update(int $projectId, int $periodId, Request $request): JsonResponse
{
    $period = ProjectProgressPeriod::where('project_id', $projectId)->findOrFail($periodId);
    $validated = $this->validatePayload($request, $projectId, $periodId);
    $merged = $this->progress->compute(array_merge($period->toArray(), $validated));
    unset($merged['physical_variance'], $merged['financial_variance']);
    $period->update($merged);

    return $this->success($period->fresh(), 'Progress period updated.');
}

public function destroy(int $projectId, int $periodId): JsonResponse
{
    ProjectProgressPeriod::where('project_id', $projectId)->findOrFail($periodId)->delete();

    return $this->success(null, 'Progress period deleted.');
}

private function validatePayload(Request $request, int $projectId, ?int $ignoreId): array
{
    $required = $ignoreId ? 'sometimes' : 'required';

    return $request->validate([
        'period_no' => [$required, 'integer', 'min:1', Rule::unique('project_progress_periods')->where('project_id', $projectId)->ignore($ignoreId)],
        'period_start' => [$required, 'date'],
        'period_end' => [$required, 'date', 'after:period_start'],
        'planning_days_completion' => ['nullable', 'integer', 'min:0'],
        'physical_scheduled_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
        'physical_actual_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
        'financial_scheduled_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
        'financial_actual_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
        'financial_actual_amount' => ['nullable', 'numeric', 'min:0'],
        'ahead_delay_days' => ['nullable', 'integer'],
        'physical_status' => ['nullable', 'in:ON TRACK,AHEAD,DELAY'],
        'financial_status' => ['nullable', 'string', 'max:50'],
        'notes' => ['nullable', 'string'],
    ]);
}
```

Routes:
```php
Route::prefix('{project}/schedule-baseline')->middleware('permission:projects.view')->group(function () {
    Route::get('/', [ScheduleBaselineController::class, 'index']);
    Route::put('/', [ScheduleBaselineController::class, 'replace'])->middleware('permission:projects.edit');
});
Route::prefix('{project}/progress-periods')->middleware('permission:projects.view')->group(function () {
    Route::get('/', [ProgressPeriodController::class, 'index']);
    Route::get('/suggest', [ProgressPeriodController::class, 'suggest']);
    Route::post('/', [ProgressPeriodController::class, 'store'])->middleware('permission:projects.edit');
    Route::put('/{period}', [ProgressPeriodController::class, 'update'])->middleware('permission:projects.edit');
    Route::delete('/{period}', [ProgressPeriodController::class, 'destroy'])->middleware('permission:projects.edit');
});
```

- [ ] **Step 8: Migrate, test, lint, commit**

Run: `php artisan migrate && php vendor/bin/phpunit tests/Unit/ProgressServiceTest.php && php artisan test tests/Feature/ReportData/ProgressPeriodTest.php && php vendor/bin/pint --test` — Expected: PASS.

```bash
git add database/migrations/2026_09_18_000005_create_project_progress_tables.php app/Models/ProjectScheduleBaseline.php app/Models/ProjectProgressPeriod.php app/Services/ReportData/ProgressService.php app/Http/Controllers/Api/ReportData/ScheduleBaselineController.php app/Http/Controllers/Api/ReportData/ProgressPeriodController.php routes/api.php tests/Unit/ProgressServiceTest.php tests/Feature/ReportData/ProgressPeriodTest.php
git commit -m "Add schedule baseline and monthly progress periods with computed variance"
```

---

### Task 6: Claim columns, correspondence groups, tender-drawing flag

Three small schema additions bundled because each is a column + validation line + one assertion.

**Files:**
- Create: `database/migrations/2026_09_18_000006_add_report_columns_to_project_invoices.php`, `database/migrations/2026_09_18_000007_add_report_group_to_correspondence_types.php`, `database/migrations/2026_09_18_000010_add_is_tender_to_drawings.php`
- Modify: `app/Models/ProjectInvoice.php`, `app/Http/Controllers/Api/ProjectInvoiceController.php`, `app/Models/CorrespondenceType.php`, `app/Models/Drawing.php`, `app/Http/Controllers/Api/DrawingController.php`
- Test: `tests/Feature/ReportData/ReportColumnsTest.php`

**Interfaces:**
- Produces: `project_invoices.wjp_current, wjp_cumulative, certified_current, certified_cumulative (decimal 15,2), evaluation_date (date)`; `correspondence_types.report_group (string nullable)` with seeded rows `adm` (Main Issues, group `adm`), `ma` (Material Approval, `ma`), `mos` (Method of Statement, `mos`), and `rfi`→`rfi`, `ncr`→`ncr`; `drawings.is_tender (bool)` filterable via `?is_tender=1`.

- [ ] **Step 1: Failing test**

```php
<?php

namespace Tests\Feature\ReportData;

use App\Models\CorrespondenceType;
use App\Models\Drawing;
use App\Models\Project;
use App\Models\ProjectInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportColumnsTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['projects.view', 'projects.edit', 'finance.view', 'finance.manage'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->editor = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $this->editor->givePermissionTo(['projects.view', 'projects.edit', 'finance.view', 'finance.manage']);
    }

    public function test_claim_report_columns_are_saved(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
        $claim = ProjectInvoice::create(['project_id' => $project->id, 'type' => 'claim', 'party_name' => 'JPS', 'amount' => 1000, 'status' => 'pending']);

        $this->actingAs($this->editor)->putJson("/api/project-invoices/{$claim->id}", [
            'wjp_current' => 50, 'wjp_cumulative' => 150, 'certified_current' => 900, 'certified_cumulative' => 2700, 'evaluation_date' => '2026-01-20',
        ])->assertOk();

        $this->assertEquals(2700, $claim->fresh()->certified_cumulative);
    }

    public function test_correspondence_types_for_the_report_exist_with_groups(): void
    {
        $this->assertSame('adm', CorrespondenceType::where('code', 'adm')->value('report_group'));
        $this->assertSame('ma', CorrespondenceType::where('code', 'ma')->value('report_group'));
        $this->assertSame('mos', CorrespondenceType::where('code', 'mos')->value('report_group'));
        $this->assertSame('rfi', CorrespondenceType::where('code', 'rfi')->value('report_group'));
        $this->assertSame('ncr', CorrespondenceType::where('code', 'ncr')->value('report_group'));
        $this->assertNull(CorrespondenceType::where('code', 'rfa')->value('report_group'));
    }

    public function test_tender_drawings_can_be_flagged_and_filtered(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
        $tender = Drawing::create(['title' => 'KEY PLAN', 'drawing_no' => 'JPS/IP/BPB/10/2025/GEN/03', 'project_id' => $project->id, 'is_tender' => true, 'status' => 'published']);
        Drawing::create(['title' => 'Shop', 'drawing_no' => 'SD/01', 'project_id' => $project->id, 'status' => 'published']);

        $res = $this->actingAs($this->editor)->getJson("/api/drawings?project_id={$project->id}&is_tender=1")->assertOk();
        $ids = collect($res->json('data.data') ?? $res->json('data'))->pluck('id')->all();
        $this->assertSame([$tender->id], $ids);
    }
}
```
(Adjust the invoice route/permission names to what `routes/api.php` actually uses for `ProjectInvoiceController@update`; check with `grep -n "project-invoices" routes/api.php`. Adjust `Drawing::create` required columns after reading the drawings migration.)

- [ ] **Step 2: Run** — Expected: FAIL (unknown columns / missing rows).

- [ ] **Step 3: Migrations**

Invoices:
```php
Schema::table('project_invoices', function (Blueprint $table) {
    $table->decimal('wjp_current', 15, 2)->nullable();
    $table->decimal('wjp_cumulative', 15, 2)->nullable();
    $table->decimal('certified_current', 15, 2)->nullable();
    $table->decimal('certified_cumulative', 15, 2)->nullable();
    $table->date('evaluation_date')->nullable();
});
```

Correspondence types:
```php
public function up(): void
{
    Schema::table('correspondence_types', function (Blueprint $table) {
        $table->string('report_group', 20)->nullable()->after('color');
    });

    DB::table('correspondence_types')->where('code', 'rfi')->update(['report_group' => 'rfi']);
    DB::table('correspondence_types')->where('code', 'ncr')->update(['report_group' => 'ncr']);

    $now = now();
    foreach ([
        ['code' => 'adm', 'name' => 'ADM', 'full_name' => 'Main Issues (Administration)', 'color' => 'slate', 'report_group' => 'adm', 'sort_order' => 10],
        ['code' => 'ma', 'name' => 'MA', 'full_name' => 'Material Approval', 'color' => 'emerald', 'report_group' => 'ma', 'sort_order' => 11],
        ['code' => 'mos', 'name' => 'MOS', 'full_name' => 'Method of Statement', 'color' => 'indigo', 'report_group' => 'mos', 'sort_order' => 12],
    ] as $row) {
        if (! DB::table('correspondence_types')->where('code', $row['code'])->exists()) {
            DB::table('correspondence_types')->insert($row + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
}

public function down(): void
{
    DB::table('correspondence_types')->whereIn('code', ['adm', 'ma', 'mos'])->delete();
    Schema::table('correspondence_types', fn (Blueprint $table) => $table->dropColumn('report_group'));
}
```

Drawings:
```php
Schema::table('drawings', function (Blueprint $table) {
    $table->boolean('is_tender')->default(false)->after('status')->index();
});
```

- [ ] **Step 4: Models + controllers**

- `ProjectInvoice::$fillable` += the 5 columns; cast `evaluation_date => 'date:Y-m-d'`. In `ProjectInvoiceController` store/update validation add
  `'wjp_current' => ['nullable','numeric','min:0'], 'wjp_cumulative' => [...same], 'certified_current' => [...], 'certified_cumulative' => [...], 'evaluation_date' => ['nullable','date']`.
- `CorrespondenceType::$fillable` += `report_group`.
- `Drawing::$fillable` += `is_tender`; cast boolean. `DrawingController` store/update: `'is_tender' => ['nullable', 'boolean']`; index: add `'is_tender'` to `$request->only([...])` and in the service/query `->when($filters['is_tender'] ?? null, fn ($q) => $q->where('is_tender', true))`.

- [ ] **Step 5: Migrate, test, lint, commit**

Run: `php artisan migrate && php artisan test tests/Feature/ReportData/ReportColumnsTest.php tests/Feature/Correspondence tests/Feature/Projects && php vendor/bin/pint --test` — Expected: PASS.

```bash
git add database/migrations/2026_09_18_000006_add_report_columns_to_project_invoices.php database/migrations/2026_09_18_000007_add_report_group_to_correspondence_types.php database/migrations/2026_09_18_000010_add_is_tender_to_drawings.php app/Models/ProjectInvoice.php app/Models/CorrespondenceType.php app/Models/Drawing.php app/Http/Controllers/Api/ProjectInvoiceController.php app/Http/Controllers/Api/DrawingController.php tests/Feature/ReportData/ReportColumnsTest.php
git commit -m "Add claim certification columns, ADM/MA/MOS correspondence types and tender-drawing flag"
```

---

### Task 7: Delay notices and testing & commissioning registers

**Files:**
- Create: `database/migrations/2026_09_18_000008_create_project_delay_notices_table.php`, `database/migrations/2026_09_18_000009_create_project_tests_table.php`, `app/Models/ProjectDelayNotice.php`, `app/Models/ProjectTest.php`, `app/Http/Controllers/Api/ReportData/DelayNoticeController.php`, `app/Http/Controllers/Api/ReportData/ProjectTestController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/ReportData/RegistersTest.php`

**Interfaces:**
- Produces: `ProjectDelayNotice {project_id, title, issue, correspondence_id?, reg_number, submitted_date, submitted_via, reply_date?, status(open|close), impact, sort_order}` with appended `duration_days`; `ProjectTest {project_id, ref_no, name, test_date, result, remarks, sort_order}`; routes `GET/POST /api/projects/{project}/delay-notices`, `PUT/DELETE /{notice}`; same shape for `/tests` (`/{test}`).

- [ ] **Step 1: Failing test**

```php
<?php

namespace Tests\Feature\ReportData;

use App\Models\Project;
use App\Models\ProjectDelayNotice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RegistersTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->editor = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $this->editor->givePermissionTo(['projects.view', 'projects.edit']);
    }

    private function project(): Project
    {
        return Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
    }

    public function test_delay_notice_duration_is_reply_minus_submitted(): void
    {
        $project = $this->project();
        $res = $this->actingAs($this->editor)->postJson("/api/projects/{$project->id}/delay-notices", [
            'title' => 'Setting-Out Plan', 'issue' => 'Delay Setting-Out Plan', 'reg_number' => 'MGE/JPS-TGOLAK/ADM/25-008',
            'submitted_date' => '2025-11-19', 'submitted_via' => 'email', 'reply_date' => '2025-12-08', 'status' => 'close',
            'impact' => 'Prevents key site activities.',
        ])->assertCreated();

        $this->assertSame(19, $res->json('data.duration_days'));
    }

    public function test_open_notice_duration_counts_to_today_and_crud_is_project_scoped(): void
    {
        $p1 = $this->project();
        $p2 = $this->project();
        $n = ProjectDelayNotice::create(['project_id' => $p1->id, 'title' => 'X', 'issue' => 'Y', 'reg_number' => 'R', 'submitted_date' => now()->subDays(5)->toDateString(), 'status' => 'open']);

        $this->assertSame(5, $n->fresh()->duration_days);
        $this->actingAs($this->editor)->deleteJson("/api/projects/{$p2->id}/delay-notices/{$n->id}")->assertNotFound();
        $this->actingAs($this->editor)->deleteJson("/api/projects/{$p1->id}/delay-notices/{$n->id}")->assertOk();
    }

    public function test_tests_register_crud(): void
    {
        $project = $this->project();
        $res = $this->actingAs($this->editor)->postJson("/api/projects/{$project->id}/tests", [
            'ref_no' => 'T-001', 'name' => 'Trial mix (jet grouting)', 'test_date' => '2026-01-09', 'result' => 'Pass',
        ])->assertCreated();
        $id = $res->json('data.id');

        $this->actingAs($this->editor)->putJson("/api/projects/{$project->id}/tests/{$id}", ['result' => 'Fail', 'remarks' => 'Retest'])->assertOk();
        $list = $this->actingAs($this->editor)->getJson("/api/projects/{$project->id}/tests")->assertOk();
        $this->assertSame('Fail', $list->json('data.0.result'));
    }
}
```

- [ ] **Step 2: Run** — Expected: FAIL.

- [ ] **Step 3: Migrations**

```php
Schema::create('project_delay_notices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->text('issue')->nullable();
    $table->foreignId('correspondence_id')->nullable()->constrained('correspondences')->nullOnDelete();
    $table->string('reg_number')->nullable();
    $table->date('submitted_date');
    $table->string('submitted_via', 50)->nullable();
    $table->date('reply_date')->nullable();
    $table->string('status', 10)->default('open');
    $table->text('impact')->nullable();
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```
(Confirm the correspondence table name with `grep -rn "Schema::create('correspondence" database/migrations`; use the real name.)

```php
Schema::create('project_tests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->string('ref_no')->nullable();
    $table->string('name');
    $table->date('test_date')->nullable();
    $table->string('result')->nullable();
    $table->text('remarks')->nullable();
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

- [ ] **Step 4: Models**

`ProjectDelayNotice`: fillable all; casts dates; `protected $appends = ['duration_days'];`
```php
public function getDurationDaysAttribute(): ?int
{
    if (! $this->submitted_date) {
        return null;
    }
    $end = $this->reply_date ?: now()->startOfDay();

    return (int) $this->submitted_date->diffInDays($end);
}
```
`ProjectTest`: fillable all; cast `test_date => 'date:Y-m-d'`.

- [ ] **Step 5: Controllers**

Both follow the same skeleton (shown for delay notices; tests controller is identical with its own fields):
```php
public function index(int $projectId): JsonResponse
{
    return $this->success(ProjectDelayNotice::where('project_id', $projectId)->orderBy('sort_order')->orderBy('submitted_date')->get());
}

public function store(int $projectId, Request $request): JsonResponse
{
    Project::findOrFail($projectId);
    $validated = $this->validatePayload($request, true);
    $validated['project_id'] = $projectId;
    $validated['created_by'] = $request->user()->id;
    $validated = $this->dropNullColumns($validated, ['status', 'sort_order']);

    return $this->created(ProjectDelayNotice::create($validated), 'Delay notice added.');
}

public function update(int $projectId, int $noticeId, Request $request): JsonResponse
{
    $notice = ProjectDelayNotice::where('project_id', $projectId)->findOrFail($noticeId);
    $notice->update($this->dropNullColumns($this->validatePayload($request, false), ['status', 'sort_order']));

    return $this->success($notice->fresh(), 'Delay notice updated.');
}

public function destroy(int $projectId, int $noticeId): JsonResponse
{
    ProjectDelayNotice::where('project_id', $projectId)->findOrFail($noticeId)->delete();

    return $this->success(null, 'Delay notice removed.');
}

private function validatePayload(Request $request, bool $creating): array
{
    $required = $creating ? 'required' : 'sometimes';

    return $request->validate([
        'title' => [$required, 'string', 'max:255'],
        'issue' => ['nullable', 'string'],
        'correspondence_id' => ['nullable', 'integer'],
        'reg_number' => ['nullable', 'string', 'max:100'],
        'submitted_date' => [$required, 'date'],
        'submitted_via' => ['nullable', 'string', 'max:50'],
        'reply_date' => ['nullable', 'date', 'after_or_equal:submitted_date'],
        'status' => ['nullable', 'in:open,close'],
        'impact' => ['nullable', 'string'],
        'sort_order' => ['nullable', 'integer', 'min:0'],
    ]);
}
```
Tests validation: `ref_no` nullable string 100, `name` required string 255, `test_date` nullable date, `result` nullable string 100, `remarks` nullable string, `sort_order` nullable int.

Routes: two groups `{project}/delay-notices` and `{project}/tests` with index/store/update/destroy, view-permission on the group and `projects.edit` on writes.

- [ ] **Step 6: Migrate, test, lint, commit**

Run: `php artisan migrate && php artisan test tests/Feature/ReportData/RegistersTest.php && php vendor/bin/pint --test` — Expected: PASS.

```bash
git add database/migrations/2026_09_18_000008_create_project_delay_notices_table.php database/migrations/2026_09_18_000009_create_project_tests_table.php app/Models/ProjectDelayNotice.php app/Models/ProjectTest.php app/Http/Controllers/Api/ReportData/DelayNoticeController.php app/Http/Controllers/Api/ReportData/ProjectTestController.php routes/api.php tests/Feature/ReportData/RegistersTest.php
git commit -m "Add delay notice and testing & commissioning registers"
```

---

### Task 8: Per-project site-log resource categories

**Files:**
- Create: `database/migrations/2026_09_18_000011_create_project_resource_categories_table.php`, `app/Models/ProjectResourceCategory.php`, `app/Services/ReportData/ResourceCategoryService.php`, `app/Http/Controllers/Api/ReportData/ResourceCategoryController.php`
- Modify: `app/Http/Controllers/Api/SiteLogController.php` (validation), `routes/api.php`
- Test: `tests/Feature/ReportData/ResourceCategoryTest.php`

**Interfaces:**
- Produces: `ProjectResourceCategory {project_id, kind(worker|machinery), group, name, sort_order, active}`; `ResourceCategoryService::namesFor(int $projectId, string $kind): array` (project list if any active rows exist, else the global defaults `SiteLogController::WORKER_TYPES` / `MACHINERY_TYPES` — move those constants onto the service as `DEFAULT_WORKERS` / `DEFAULT_MACHINERY` and keep the controller reading them from there); `POST /api/projects/{project}/resource-categories/seed-defaults?kind=worker` copies defaults into the project; `GET /api/projects/{project}/resource-categories?kind=`, `PUT` bulk replace `rows: [{group, name, sort_order, active}]` per kind.

- [ ] **Step 1: Failing test**

```php
<?php

namespace Tests\Feature\ReportData;

use App\Models\Project;
use App\Models\ProjectResourceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ResourceCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->editor = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $this->editor->givePermissionTo(['projects.view', 'projects.edit']);
    }

    private function project(): Project
    {
        return Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
    }

    public function test_project_list_replaces_defaults_for_site_log_validation(): void
    {
        $project = $this->project();

        // Default list still accepted while the project has no categories.
        $this->actingAs($this->editor)->postJson("/api/projects/{$project->id}/site-logs", [
            'log_date' => '2026-01-05', 'workers' => [['worker_type' => 'General Worker', 'count' => 30]],
        ])->assertCreated();

        $this->actingAs($this->editor)->putJson("/api/projects/{$project->id}/resource-categories?kind=worker", ['rows' => [
            ['group' => 'Management Team', 'name' => 'Project Manager', 'sort_order' => 0],
            ['group' => 'Tradesman', 'name' => 'China Worker', 'sort_order' => 1],
        ]])->assertOk();

        $this->actingAs($this->editor)->postJson("/api/projects/{$project->id}/site-logs", [
            'log_date' => '2026-01-06', 'workers' => [['worker_type' => 'China Worker', 'count' => 8]],
        ])->assertCreated();

        $this->actingAs($this->editor)->postJson("/api/projects/{$project->id}/site-logs", [
            'log_date' => '2026-01-07', 'workers' => [['worker_type' => 'General Worker', 'count' => 1]],
        ])->assertStatus(422);
    }

    public function test_seed_defaults_copies_global_lists(): void
    {
        $project = $this->project();
        $this->actingAs($this->editor)->postJson("/api/projects/{$project->id}/resource-categories/seed-defaults?kind=machinery")->assertOk();
        $this->assertTrue(ProjectResourceCategory::where('project_id', $project->id)->where('kind', 'machinery')->where('name', 'Excavator')->exists());
    }
}
```

- [ ] **Step 2: Run** — Expected: FAIL.

- [ ] **Step 3: Migration + model**

```php
Schema::create('project_resource_categories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->string('kind', 20);
    $table->string('group', 100)->nullable();
    $table->string('name');
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->boolean('active')->default(true);
    $table->timestamps();
    $table->unique(['project_id', 'kind', 'name']);
});
```
Model: fillable all; cast `active => boolean`.

- [ ] **Step 4: Service**

```php
<?php

namespace App\Services\ReportData;

use App\Models\ProjectResourceCategory;

class ResourceCategoryService
{
    public const DEFAULT_WORKERS = ['General Worker', 'Operator', 'Bar Bender', 'Carpenter', 'Steel Fixer', 'Mason', 'Electrician', 'Plumber', 'Welder', 'Supervisor', 'Other'];

    public const DEFAULT_MACHINERY = ['Excavator', 'Bulldozer', 'Crane', 'Compactor', 'Loader', 'Dump Truck', 'Generator', 'Other'];

    /** @return array<int, string> */
    public function namesFor(int $projectId, string $kind): array
    {
        $names = ProjectResourceCategory::where('project_id', $projectId)->where('kind', $kind)->where('active', true)
            ->orderBy('sort_order')->pluck('name')->all();

        return $names ?: ($kind === 'worker' ? self::DEFAULT_WORKERS : self::DEFAULT_MACHINERY);
    }

    public function seedDefaults(int $projectId, string $kind): void
    {
        $defaults = $kind === 'worker' ? self::DEFAULT_WORKERS : self::DEFAULT_MACHINERY;
        foreach ($defaults as $i => $name) {
            ProjectResourceCategory::firstOrCreate(
                ['project_id' => $projectId, 'kind' => $kind, 'name' => $name],
                ['group' => $kind === 'worker' ? 'Tradesman' : null, 'sort_order' => $i, 'active' => true],
            );
        }
    }
}
```

In `SiteLogController`: delete the `MACHINERY_TYPES` / `WORKER_TYPES` constants, inject `ResourceCategoryService $categories` in the constructor, and in `validatePayload()` (the method around line 350) replace the two `implode` lines with
```php
$machineryTypes = implode(',', $this->categories->namesFor($projectId, 'machinery'));
$workerTypes = implode(',', $this->categories->namesFor($projectId, 'worker'));
```
— `validatePayload` must receive `$projectId`; update its two call sites in `store()`/`update()`. Keep the `in:` rule so existing tests still pass with defaults.

- [ ] **Step 5: Controller + routes**

```php
public function index(int $projectId, Request $request): JsonResponse
{
    $kind = $request->validate(['kind' => ['required', 'in:worker,machinery']])['kind'];

    return $this->success([
        'rows' => ProjectResourceCategory::where('project_id', $projectId)->where('kind', $kind)->orderBy('sort_order')->get(),
        'effective' => $this->categories->namesFor($projectId, $kind),
    ]);
}

public function replace(int $projectId, Request $request): JsonResponse
{
    Project::findOrFail($projectId);
    $validated = $request->validate([
        'kind' => ['required', 'in:worker,machinery'],
        'rows' => ['present', 'array', 'max:100'],
        'rows.*.group' => ['nullable', 'string', 'max:100'],
        'rows.*.name' => ['required', 'string', 'max:100', 'distinct'],
        'rows.*.sort_order' => ['nullable', 'integer', 'min:0'],
        'rows.*.active' => ['nullable', 'boolean'],
    ]);

    DB::transaction(function () use ($projectId, $validated) {
        ProjectResourceCategory::where('project_id', $projectId)->where('kind', $validated['kind'])->delete();
        foreach ($validated['rows'] as $i => $r) {
            ProjectResourceCategory::create([
                'project_id' => $projectId, 'kind' => $validated['kind'], 'group' => $r['group'] ?? null,
                'name' => $r['name'], 'sort_order' => $r['sort_order'] ?? $i, 'active' => $r['active'] ?? true,
            ]);
        }
    });

    return $this->index($projectId, $request);
}

public function seedDefaults(int $projectId, Request $request): JsonResponse
{
    Project::findOrFail($projectId);
    $kind = $request->validate(['kind' => ['required', 'in:worker,machinery']])['kind'];
    $this->categories->seedDefaults($projectId, $kind);

    return $this->index($projectId, $request);
}
```
Routes: `GET /{project}/resource-categories`, `PUT` (replace), `POST /seed-defaults` — view on group, edit on writes. Note `kind` for `PUT` comes from the query string; merge it: in `replace()` call `$request->merge(['kind' => $request->query('kind', $request->input('kind'))])` before validating.

- [ ] **Step 6: Migrate, test, lint, commit**

Run: `php artisan migrate && php artisan test tests/Feature/ReportData/ResourceCategoryTest.php tests/Feature/Projects && php vendor/bin/pint --test` — Expected: PASS (existing SiteLog tests still green with defaults).

```bash
git add database/migrations/2026_09_18_000011_create_project_resource_categories_table.php app/Models/ProjectResourceCategory.php app/Services/ReportData/ResourceCategoryService.php app/Http/Controllers/Api/ReportData/ResourceCategoryController.php app/Http/Controllers/Api/SiteLogController.php routes/api.php tests/Feature/ReportData/ResourceCategoryTest.php
git commit -m "Make site-log worker and machinery categories configurable per project"
```

---

### Task 9: Report images (location, site access, progress)

**Files:**
- Create: `database/migrations/2026_09_18_000012_create_report_images_table.php`, `app/Models/ReportImage.php`, `app/Http/Controllers/Api/ReportData/ReportImageController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/ReportData/ReportImageTest.php`

**Interfaces:**
- Produces: `ReportImage {project_id, section(location|site_access|progress_key_plan|progress), label, period_id?, file_path, caption, sort_order, taken_on}`; routes `GET /api/projects/{project}/report-images?section=&period_id=`, `POST` (multipart `image`, plus fields), `PUT /{image}` (label/caption/sort/period/taken_on), `DELETE /{image}`, `GET /{image}/view` (inline). Upload downscales with GD when available (max 1600px wide, JPEG quality 82) and silently keeps the original otherwise.

- [ ] **Step 1: Failing test**

```php
<?php

namespace Tests\Feature\ReportData;

use App\Models\Project;
use App\Models\ProjectProgressPeriod;
use App\Models\ReportImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportImageTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->editor = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $this->editor->givePermissionTo(['projects.view', 'projects.edit']);
    }

    public function test_upload_list_filter_and_inline_view(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
        $period = ProjectProgressPeriod::create(['project_id' => $project->id, 'period_no' => 3, 'period_start' => '2025-12-16', 'period_end' => '2026-01-15']);

        $res = $this->actingAs($this->editor)->post("/api/projects/{$project->id}/report-images", [
            'image' => UploadedFile::fake()->image('aerial1.jpg', 800, 600),
            'section' => 'progress', 'label' => 'Aerial 1', 'period_id' => $period->id, 'taken_on' => '2026-01-15',
        ])->assertCreated();
        $id = $res->json('data.id');
        Storage::disk('local')->assertExists(ReportImage::find($id)->file_path);

        $this->actingAs($this->editor)->post("/api/projects/{$project->id}/report-images", [
            'image' => UploadedFile::fake()->image('map.png'), 'section' => 'location',
        ])->assertCreated();

        $list = $this->actingAs($this->editor)->getJson("/api/projects/{$project->id}/report-images?section=progress")->assertOk();
        $this->assertCount(1, $list->json('data'));
        $this->assertSame('Aerial 1', $list->json('data.0.label'));

        $view = $this->actingAs($this->editor)->get("/api/projects/{$project->id}/report-images/{$id}/view");
        $view->assertOk();
        $this->assertStringContainsString('inline', $view->headers->get('content-disposition'));
    }

    public function test_bad_extension_and_bad_section_are_rejected(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);

        $this->actingAs($this->editor)->post("/api/projects/{$project->id}/report-images", [
            'image' => UploadedFile::fake()->create('x.exe', 10), 'section' => 'location',
        ])->assertStatus(422);
        $this->actingAs($this->editor)->post("/api/projects/{$project->id}/report-images", [
            'image' => UploadedFile::fake()->image('x.jpg'), 'section' => 'nope',
        ])->assertStatus(422);
    }
}
```

- [ ] **Step 2: Run** — Expected: FAIL.

- [ ] **Step 3: Migration + model**

```php
Schema::create('report_images', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->string('section', 30);
    $table->string('label')->nullable();
    $table->foreignId('period_id')->nullable()->constrained('project_progress_periods')->nullOnDelete();
    $table->string('file_path');
    $table->string('file_name');
    $table->string('caption')->nullable();
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->date('taken_on')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->index(['project_id', 'section', 'period_id']);
});
```
Model `ReportImage`: `SECTIONS = ['location','site_access','progress_key_plan','progress']`; fillable all; cast `taken_on => 'date:Y-m-d'`; `period()` belongsTo `ProjectProgressPeriod`.

- [ ] **Step 4: Controller**

```php
<?php

namespace App\Http\Controllers\Api\ReportData;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ReportImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ReportImageController extends Controller
{
    private const TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];

    public function index(int $projectId, Request $request): JsonResponse
    {
        $q = ReportImage::where('project_id', $projectId)->orderBy('section')->orderBy('sort_order')->orderBy('id');
        if ($request->section) {
            $q->where('section', $request->section);
        }
        if ($request->filled('period_id')) {
            $q->where('period_id', $request->integer('period_id'));
        }

        return $this->success($q->get());
    }

    public function store(int $projectId, Request $request): JsonResponse
    {
        Project::findOrFail($projectId);
        $validated = $request->validate([
            'image' => ['required', 'file', 'max:20480', 'extensions:png,jpg,jpeg,webp'],
            'section' => ['required', 'in:'.implode(',', ReportImage::SECTIONS)],
            'label' => ['nullable', 'string', 'max:100'],
            'period_id' => ['nullable', 'integer', 'exists:project_progress_periods,id,project_id,'.$projectId],
            'caption' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'taken_on' => ['nullable', 'date'],
        ]);
        $file = $request->file('image');
        unset($validated['image']);

        $path = $file->store("projects/{$projectId}/report-images", 'local');
        $this->downscale(Storage::disk('local')->path($path));

        $image = ReportImage::create($validated + [
            'project_id' => $projectId,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'created_by' => $request->user()->id,
        ]);

        return $this->created($image, 'Image uploaded.');
    }

    public function update(int $projectId, int $imageId, Request $request): JsonResponse
    {
        $image = ReportImage::where('project_id', $projectId)->findOrFail($imageId);
        $image->update($request->validate([
            'section' => ['sometimes', 'in:'.implode(',', ReportImage::SECTIONS)],
            'label' => ['nullable', 'string', 'max:100'],
            'period_id' => ['nullable', 'integer', 'exists:project_progress_periods,id,project_id,'.$projectId],
            'caption' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'taken_on' => ['nullable', 'date'],
        ]));

        return $this->success($image->fresh(), 'Image updated.');
    }

    public function destroy(int $projectId, int $imageId): JsonResponse
    {
        $image = ReportImage::where('project_id', $projectId)->findOrFail($imageId);
        Storage::disk('local')->delete($image->file_path);
        $image->delete();

        return $this->success(null, 'Image removed.');
    }

    public function view(int $projectId, int $imageId)
    {
        $image = ReportImage::where('project_id', $projectId)->findOrFail($imageId);
        abort_unless(Storage::disk('local')->exists($image->file_path), 404);
        $ext = strtolower(pathinfo($image->file_path, PATHINFO_EXTENSION));

        return response()->file(Storage::disk('local')->path($image->file_path), [
            'Content-Type' => self::TYPES[$ext] ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($image->file_name).'"',
        ]);
    }

    /** Best-effort: shrink large photos so a 40-page PDF stays within memory. Requires GD; no-op otherwise. */
    private function downscale(string $absolutePath, int $maxWidth = 1600): void
    {
        if (! function_exists('imagecreatefromstring')) {
            return;
        }
        $info = @getimagesize($absolutePath);
        if (! $info || $info[0] <= $maxWidth) {
            return;
        }
        $src = @imagecreatefromstring((string) file_get_contents($absolutePath));
        if (! $src) {
            return;
        }
        $ratio = $maxWidth / $info[0];
        $dst = imagecreatetruecolor($maxWidth, (int) round($info[1] * $ratio));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxWidth, (int) round($info[1] * $ratio), $info[0], $info[1]);
        match ($info[2]) {
            IMAGETYPE_PNG => imagepng($dst, $absolutePath, 6),
            IMAGETYPE_WEBP => imagewebp($dst, $absolutePath, 82),
            default => imagejpeg($dst, $absolutePath, 82),
        };
        imagedestroy($src);
        imagedestroy($dst);
    }
}
```

Routes: `{project}/report-images` — `GET /`, `POST /` (edit), `PUT /{image}` (edit), `DELETE /{image}` (edit), `GET /{image}/view`.

- [ ] **Step 5: Migrate, test, lint, commit**

Run: `php artisan migrate && php artisan test tests/Feature/ReportData && php vendor/bin/pint --test` — Expected: all ReportData tests PASS.

```bash
git add database/migrations/2026_09_18_000012_create_report_images_table.php app/Models/ReportImage.php app/Http/Controllers/Api/ReportData/ReportImageController.php routes/api.php tests/Feature/ReportData/ReportImageTest.php
git commit -m "Add project report images for location, site access and progress photos"
```

---

### Task 10: Frontend service + Report Data tab skeleton + Contract Particulars panel

**Files:**
- Create: `resources/js/services/reportDataService.js`, `resources/js/pages/projects/report-data/ReportDataTab.jsx`, `resources/js/pages/projects/report-data/ContractParticularsPanel.jsx`
- Modify: `resources/js/pages/projects/ProjectDetail.jsx` (tabs array + render line), `resources/js/pages/projects/contracts/ContractDetail.jsx` (main-contract toggle)

**Interfaces:**
- Produces: `reportDataService` with `getContractParticulars(projectId)`, `updateContractParticulars(projectId, data)`, `listParties`, `createParty`, `updateParty`, `deleteParty`, `uploadPartyLogo(projectId, partyId, formData)`, `getPartyLogoUrl(projectId, partyId)`, `getOrgChart`, `updateOrgChart`, `getBaseline`, `replaceBaseline`, `listPeriods`, `suggestPeriod(projectId, periodEnd)`, `createPeriod`, `updatePeriod`, `deletePeriod`, `listDelayNotices/create/update/delete`, `listTests/create/update/delete`, `getCategories(projectId, kind)`, `replaceCategories(projectId, kind, rows)`, `seedCategories(projectId, kind)`, `listImages(projectId, params)`, `uploadImage(projectId, formData)`, `updateImage`, `deleteImage`, `getImageViewUrl(projectId, imageId)` — every function returns `response.data`.
- `ReportDataTab({ project, canEdit })` renders a left list of panels (Contract Particulars, Parties, Org Chart, Progress & Baseline, Registers, Site-Log Categories, Report Images) and the selected panel; each panel receives `{ project, canEdit }`.

- [ ] **Step 1: Service file**

```js
import apiClient from './apiClient';

const p = (projectId, path = '') => `/projects/${projectId}${path}`;

const reportDataService = {
    async getContractParticulars(projectId) { return (await apiClient.get(p(projectId, '/contract-particulars'))).data; },
    async updateContractParticulars(projectId, data) { return (await apiClient.put(p(projectId, '/contract-particulars'), data)).data; },

    async listParties(projectId) { return (await apiClient.get(p(projectId, '/parties'))).data; },
    async createParty(projectId, data) { return (await apiClient.post(p(projectId, '/parties'), data)).data; },
    async updateParty(projectId, partyId, data) { return (await apiClient.put(p(projectId, `/parties/${partyId}`), data)).data; },
    async deleteParty(projectId, partyId) { return (await apiClient.delete(p(projectId, `/parties/${partyId}`))).data; },
    async uploadPartyLogo(projectId, partyId, formData) {
        return (await apiClient.post(p(projectId, `/parties/${partyId}/logo`), formData, { headers: { 'Content-Type': 'multipart/form-data' } })).data;
    },
    getPartyLogoUrl(projectId, partyId) { return `/api${p(projectId, `/parties/${partyId}/logo`)}`; },

    async getOrgChart(projectId) { return (await apiClient.get(p(projectId, '/org-chart'))).data; },
    async updateOrgChart(projectId, members) { return (await apiClient.put(p(projectId, '/org-chart'), { members })).data; },

    async getBaseline(projectId) { return (await apiClient.get(p(projectId, '/schedule-baseline'))).data; },
    async replaceBaseline(projectId, rows) { return (await apiClient.put(p(projectId, '/schedule-baseline'), { rows })).data; },
    async listPeriods(projectId) { return (await apiClient.get(p(projectId, '/progress-periods'))).data; },
    async suggestPeriod(projectId, periodEnd) { return (await apiClient.get(p(projectId, '/progress-periods/suggest'), { params: { period_end: periodEnd } })).data; },
    async createPeriod(projectId, data) { return (await apiClient.post(p(projectId, '/progress-periods'), data)).data; },
    async updatePeriod(projectId, id, data) { return (await apiClient.put(p(projectId, `/progress-periods/${id}`), data)).data; },
    async deletePeriod(projectId, id) { return (await apiClient.delete(p(projectId, `/progress-periods/${id}`))).data; },

    async listDelayNotices(projectId) { return (await apiClient.get(p(projectId, '/delay-notices'))).data; },
    async createDelayNotice(projectId, data) { return (await apiClient.post(p(projectId, '/delay-notices'), data)).data; },
    async updateDelayNotice(projectId, id, data) { return (await apiClient.put(p(projectId, `/delay-notices/${id}`), data)).data; },
    async deleteDelayNotice(projectId, id) { return (await apiClient.delete(p(projectId, `/delay-notices/${id}`))).data; },

    async listTests(projectId) { return (await apiClient.get(p(projectId, '/tests'))).data; },
    async createTest(projectId, data) { return (await apiClient.post(p(projectId, '/tests'), data)).data; },
    async updateTest(projectId, id, data) { return (await apiClient.put(p(projectId, `/tests/${id}`), data)).data; },
    async deleteTest(projectId, id) { return (await apiClient.delete(p(projectId, `/tests/${id}`))).data; },

    async getCategories(projectId, kind) { return (await apiClient.get(p(projectId, '/resource-categories'), { params: { kind } })).data; },
    async replaceCategories(projectId, kind, rows) { return (await apiClient.put(p(projectId, `/resource-categories?kind=${kind}`), { kind, rows })).data; },
    async seedCategories(projectId, kind) { return (await apiClient.post(p(projectId, `/resource-categories/seed-defaults?kind=${kind}`))).data; },

    async listImages(projectId, params = {}) { return (await apiClient.get(p(projectId, '/report-images'), { params })).data; },
    async uploadImage(projectId, formData) {
        return (await apiClient.post(p(projectId, '/report-images'), formData, { headers: { 'Content-Type': 'multipart/form-data' } })).data;
    },
    async updateImage(projectId, id, data) { return (await apiClient.put(p(projectId, `/report-images/${id}`), data)).data; },
    async deleteImage(projectId, id) { return (await apiClient.delete(p(projectId, `/report-images/${id}`))).data; },
    getImageViewUrl(projectId, id) { return `/api${p(projectId, `/report-images/${id}/view`)}`; },
};

export default reportDataService;
```

- [ ] **Step 2: Tab skeleton**

```jsx
import { useState } from 'react';
import ContractParticularsPanel from './ContractParticularsPanel';

const PANELS = [
    { id: 'particulars', label: 'Contract Particulars', component: ContractParticularsPanel },
    // Later tasks append: parties, org-chart, progress, registers, categories, images
];

export default function ReportDataTab({ project, canEdit }) {
    const [active, setActive] = useState(PANELS[0].id);
    const Panel = PANELS.find((p) => p.id === active)?.component;

    return (
        <div className="grid gap-6 lg:grid-cols-[220px_1fr]">
            <nav className="rounded-xl bg-white p-2 shadow-sm ring-1 ring-gray-200">
                <p className="px-3 py-2 text-xs font-semibold uppercase text-gray-500">Report Data</p>
                {PANELS.map((p) => (
                    <button
                        key={p.id}
                        type="button"
                        onClick={() => setActive(p.id)}
                        className={`block w-full rounded-lg px-3 py-2 text-left text-sm ${active === p.id ? 'bg-primary-50 font-medium text-primary-700' : 'text-gray-700 hover:bg-gray-50'}`}
                    >
                        {p.label}
                    </button>
                ))}
            </nav>
            <div>{Panel && <Panel project={project} canEdit={canEdit} />}</div>
        </div>
    );
}
```

In `ProjectDetail.jsx`: import `ReportDataTab from './report-data/ReportDataTab'`; add `{ id: 'report-data', label: 'Report Data', icon: HiOutlineClipboardList }` after `discussions` in `tabs`; add `{activeTab === 'report-data' && <ReportDataTab project={project} canEdit={canEdit} />}` after the discussions line.

- [ ] **Step 3: Contract Particulars panel**

```jsx
import { useEffect, useState } from 'react';
import toast from 'react-hot-toast';
import reportDataService from '@/services/reportDataService';
import LoadingSpinner from '@/components/LoadingSpinner';

const input = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500';
const emptyInsurance = () => ({ type: '', insurer: '', policy_no: '', period_from: '', period_to: '', maintenance_from: '', maintenance_to: '' });
const FIELDS = [
    ['contract_sum', 'Contract Sum (RM)', 'number'], ['performance_bond_amount', 'Performance Guarantee / WJP (RM)', 'number'],
    ['duration_months', 'Duration of Completion (months)', 'number'], ['dlp_months', 'DLP (months)', 'number'],
    ['lad_per_day', 'LAD per day (RM)', 'number'], ['cidb_registration', 'CIDB Registration', 'text'],
    ['possession_date', 'Possession Date', 'date'], ['completion_date', 'Completion Date', 'date'],
    ['dlp_start_date', 'DLP Start', 'date'], ['dlp_end_date', 'DLP End', 'date'],
];

export default function ContractParticularsPanel({ project, canEdit }) {
    const [loading, setLoading] = useState(true);
    const [missing, setMissing] = useState(false);
    const [words, setWords] = useState({});
    const [form, setForm] = useState(null);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        setLoading(true);
        reportDataService.getContractParticulars(project.id)
            .then((res) => {
                const c = res.data.contract;
                setForm({
                    ...Object.fromEntries(FIELDS.map(([k]) => [k, c[k] ?? ''])),
                    insurances: c.insurances?.length ? c.insurances : [emptyInsurance()],
                });
                setWords({ sum: res.data.contract_sum_words, bond: res.data.performance_bond_words });
            })
            .catch((err) => { if (err.response?.status === 404) setMissing(true); else toast.error('Failed to load particulars'); })
            .finally(() => setLoading(false));
    }, [project.id]);

    const save = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const payload = { ...form, insurances: form.insurances.filter((i) => i.type) };
            const res = await reportDataService.updateContractParticulars(project.id, payload);
            setWords({ sum: res.data.contract_sum_words, bond: res.data.performance_bond_words });
            toast.success('Contract particulars saved');
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save');
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <LoadingSpinner />;
    if (missing) {
        return (
            <div className="rounded-xl bg-white p-6 text-sm text-gray-600 shadow-sm ring-1 ring-gray-200">
                No main contract is set for this project. Open <b>Projects › Contracts</b>, edit the contract and tick <b>Main contract</b>.
            </div>
        );
    }

    const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));
    const setIns = (i, k) => (e) => setForm((f) => ({ ...f, insurances: f.insurances.map((row, idx) => (idx === i ? { ...row, [k]: e.target.value } : row)) }));

    return (
        <form onSubmit={save} className="space-y-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <h2 className="text-lg font-semibold text-gray-900">Contract Particulars</h2>
            <div className="grid gap-4 sm:grid-cols-2">
                {FIELDS.map(([k, label, type]) => (
                    <div key={k}>
                        <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
                        <input type={type} step={type === 'number' ? '0.01' : undefined} value={form[k] ?? ''} onChange={set(k)} disabled={!canEdit} className={input} />
                        {k === 'contract_sum' && words.sum && <p className="mt-1 text-xs text-gray-500">{words.sum}</p>}
                        {k === 'performance_bond_amount' && words.bond && <p className="mt-1 text-xs text-gray-500">{words.bond}</p>}
                    </div>
                ))}
            </div>

            <div>
                <div className="mb-2 flex items-center justify-between">
                    <p className="text-sm font-semibold text-gray-900">Insurance</p>
                    {canEdit && <button type="button" onClick={() => setForm((f) => ({ ...f, insurances: [...f.insurances, emptyInsurance()] }))} className="text-sm font-medium text-primary-600 hover:text-primary-700">+ Add policy</button>}
                </div>
                <div className="space-y-3">
                    {form.insurances.map((ins, i) => (
                        <div key={i} className="grid gap-3 rounded-lg border border-gray-200 p-3 sm:grid-cols-4">
                            <input placeholder="Type (e.g. Contractor's All Risk)" value={ins.type} onChange={setIns(i, 'type')} disabled={!canEdit} className={`${input} sm:col-span-2`} />
                            <input placeholder="Insurer" value={ins.insurer} onChange={setIns(i, 'insurer')} disabled={!canEdit} className={input} />
                            <input placeholder="Policy No." value={ins.policy_no} onChange={setIns(i, 'policy_no')} disabled={!canEdit} className={input} />
                            <label className="text-xs text-gray-500">Period from<input type="date" value={ins.period_from || ''} onChange={setIns(i, 'period_from')} disabled={!canEdit} className={input} /></label>
                            <label className="text-xs text-gray-500">Period to<input type="date" value={ins.period_to || ''} onChange={setIns(i, 'period_to')} disabled={!canEdit} className={input} /></label>
                            <label className="text-xs text-gray-500">Maintenance from<input type="date" value={ins.maintenance_from || ''} onChange={setIns(i, 'maintenance_from')} disabled={!canEdit} className={input} /></label>
                            <label className="text-xs text-gray-500">Maintenance to<input type="date" value={ins.maintenance_to || ''} onChange={setIns(i, 'maintenance_to')} disabled={!canEdit} className={input} /></label>
                            {canEdit && <button type="button" onClick={() => setForm((f) => ({ ...f, insurances: f.insurances.filter((_, idx) => idx !== i) }))} className="text-left text-xs text-red-600 hover:underline sm:col-span-4">Remove policy</button>}
                        </div>
                    ))}
                </div>
            </div>

            {canEdit && (
                <div className="flex justify-end">
                    <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving…' : 'Save particulars'}</button>
                </div>
            )}
        </form>
    );
}
```

- [ ] **Step 4: Main-contract toggle in `ContractDetail.jsx`**

In `EditContractModal`'s form state add `is_main: !!contract.is_main`, render a checkbox "Main contract (used for reports)" under Status, and include `is_main` in the PUT payload. Show a small "MAIN" badge next to the contract title on the detail header when `contract.is_main`.

- [ ] **Step 5: Build, manual check, commit**

Run: `npm run build` — Expected: success. Open `http://mge-system.test`, project PR4829 → Report Data → Contract Particulars: when no main contract, the guidance card appears; tick Main contract on a contract, reload, fill values, Save, confirm the amount-in-words line updates.

```bash
git add resources/js/services/reportDataService.js resources/js/pages/projects/report-data resources/js/pages/projects/ProjectDetail.jsx resources/js/pages/projects/contracts/ContractDetail.jsx public/build/manifest.json public/build/assets
git commit -m "Add Report Data tab with contract particulars panel"
```
(Stage the new hashed assets and `git rm --cached` the replaced ones, as done in previous commits.)

---

### Task 11: Parties and Org Chart panels

**Files:**
- Create: `resources/js/pages/projects/report-data/PartiesPanel.jsx`, `resources/js/pages/projects/report-data/OrgChartPanel.jsx`
- Modify: `resources/js/pages/projects/report-data/ReportDataTab.jsx` (register both panels)

**Interfaces:**
- Consumes: `reportDataService.listParties/createParty/updateParty/deleteParty/uploadPartyLogo/getPartyLogoUrl/getOrgChart/updateOrgChart` and `project.members` (array of users with `id`, `full_name`).

- [ ] **Step 1: PartiesPanel**

Behaviour: list of party cards ordered by `sort_order`, each showing the report role label (or the existing `type` when `report_role` is null), company (`name`), address, logo thumbnail (`<img src={getPartyLogoUrl}>` when `logo_path`), contacts list. Buttons (canEdit): Add party, Edit (opens inline form with report role select over `ROLES`, role_label when `other`, company name, address, contacts rows name/designation/phone/email with add/remove), Upload logo (hidden `<input type="file" accept=".png,.jpg,.jpeg,.webp">`), Delete (`useConfirm`). Role labels map:

```js
const ROLES = [
    ['owner', 'Project Owner'], ['superintending_officer', 'Superintending Officer (SO)'], ['so_representative', "Representative of the SO"],
    ['district_engineer', 'District Engineer'], ['quantity_surveyor', 'Quantity Surveyor'], ['consultant', 'Consultant'], ['contractor', 'Contractor'], ['other', 'Other'],
];
```
Save calls `createParty`/`updateParty` with `{ name, type, report_role, role_label, address, sort_order, contacts }` (`name` is the company; `type` is the existing correspondence party type — default it from `report_role`: owner/superintending_officer/so_representative/district_engineer/quantity_surveyor → `client`, consultant → `consultant`, contractor → `main_contractor`, other → `other`), then reloads the list and toasts. Follow the form styling from `ContractParticularsPanel` (`input` class) and the card styling from the Documents tab.

- [ ] **Step 2: OrgChartPanel**

Behaviour: table of active project members (`project.members`) with columns Name, Designation (text input), Reports to (select of other members or "— Top level —"), Order (number). Loads current values from `getOrgChart` (flatten the tree into a map by `user_id`). Save → `updateOrgChart(project.id, rows)`. Below the table, a read-only preview rendering the tree returned by the API as nested lists (`<ul>` with indent) — no diagram library in Phase A. If `project.members` is empty, show "Add members on the Overview tab first."

- [ ] **Step 3: Register panels**

In `ReportDataTab.jsx` `PANELS` add `{ id: 'parties', label: 'Parties & Contacts', component: PartiesPanel }` and `{ id: 'org-chart', label: 'Organisation Chart', component: OrgChartPanel }`.

- [ ] **Step 4: Build, manual check, commit**

Run: `npm run build`. Check: add a Consultant with two contacts, upload a PNG logo, see thumbnail; set designations and a reporting line; the preview nests correctly.

```bash
git add resources/js/pages/projects/report-data public/build/manifest.json public/build/assets
git commit -m "Add parties and organisation chart panels to Report Data"
```

---

### Task 12: Progress & Baseline panel

**Files:**
- Create: `resources/js/pages/projects/report-data/ProgressPanel.jsx`
- Modify: `ReportDataTab.jsx`

**Interfaces:**
- Consumes: `getBaseline/replaceBaseline/listPeriods/suggestPeriod/createPeriod/updatePeriod/deletePeriod`.

- [ ] **Step 1: Baseline grid**

Section "Schedule baseline (from CPM)": editable grid, one row per month (`month` as `YYYY-MM` input, `scheduled_physical_pct`, `scheduled_financial_amount`, `scheduled_financial_pct`), "Add month" appends the month after the last row, "Generate months" prompts for start/end `YYYY-MM` and fills empty rows, "Save baseline" → `replaceBaseline(project.id, rows)`. Warn (toast) if a month is duplicated before sending.

- [ ] **Step 2: Periods list + form**

Section "Progress periods": table of periods (No., Period, Physical sched/actual/variance, Financial sched/actual/variance, Ahead/Delay, Status). "New period" opens a form with a `period_end` date; on change call `suggestPeriod` and fill period_no, period_start, scheduled values, financial actual, planning days (all editable). Fields: period_no, period_start, period_end, planning_days_completion, physical_scheduled_pct, physical_actual_pct, financial_scheduled_pct, financial_actual_pct, financial_actual_amount, ahead_delay_days (blank = auto), physical_status (select blank/ON TRACK/AHEAD/DELAY, blank = auto), financial_status text, notes. Edit reuses the form; Delete uses `useConfirm`. After save, show the computed `physical_variance` / `ahead_delay_days` from the response.

- [ ] **Step 3: Register + build + commit**

Add `{ id: 'progress', label: 'Progress & Baseline', component: ProgressPanel }`. Run `npm run build`; check that "New period" with end date 2026-01-15 suggests 2025-12-16 → 2026-01-15 for a project with cutoff 15.

```bash
git add resources/js/pages/projects/report-data public/build/manifest.json public/build/assets
git commit -m "Add progress periods and schedule baseline panel"
```

---

### Task 13: Registers, Categories and Images panels

**Files:**
- Create: `resources/js/pages/projects/report-data/RegistersPanel.jsx`, `ResourceCategoriesPanel.jsx`, `ReportImagesPanel.jsx`
- Modify: `ReportDataTab.jsx`

**Interfaces:**
- Consumes: delay-notice, test, category and image service functions from Task 10.

- [ ] **Step 1: RegistersPanel**

Two stacked cards. **Notice of Delay**: table (No, Title, Issue, Reg. Number, Submitted, Reply, Duration (days), Status, Impact) + inline add/edit form (title, issue, reg_number, submitted_date, submitted_via, reply_date, status select open/close, impact textarea) + delete with confirm. **Testing & Commissioning**: table (Ref. No, Name of Test, Date, Result, Remarks) + form + delete.

- [ ] **Step 2: ResourceCategoriesPanel**

Toggle `kind` (Workers / Machinery). Loads `getCategories`; shows "Using default list" notice with a **Use defaults as starting point** button (`seedCategories`) when `rows` is empty. Editable grid: group (select "Management Team"/"Tradesman" for workers, free text for machinery), name, order, active checkbox; add row; remove row; **Save list** → `replaceCategories`. Explain under the title: "These names are what the Site Log form offers and what the monthly report tabulates."

- [ ] **Step 3: ReportImagesPanel**

Section tabs: Location, Site Access, Progress Key Plan, Progress Photos. Grid of thumbnails (`<img src={getImageViewUrl}>`), each with label, caption, taken-on, and for Progress Photos a period select (from `listPeriods`), plus Remove (confirm). Upload area: hidden multi-file input (`accept=".png,.jpg,.jpeg,.webp"`), each file posted separately with the current section; for Progress Photos the label input is required before upload ("Aerial 1", "Overall Site View") so Previous/Current pairing works later. Reorder with ▲/▼ buttons that PUT `sort_order`.

- [ ] **Step 4: Register + build + manual check + commit**

Add `registers` ("Delay Notices & Tests"), `categories` ("Site-Log Categories"), `images` ("Report Images") to `PANELS`. Run `npm run build`; check: save a worker list with "China Worker", open Site Logs → New Entry → the Workers dropdown offers "China Worker"; upload two Progress photos labelled "Aerial 1" for two different periods and see both thumbnails.

```bash
git add resources/js/pages/projects/report-data public/build/manifest.json public/build/assets
git commit -m "Add registers, site-log categories and report images panels"
```

---

### Task 14: Local demo data + Panduan note + full verification

**Files:**
- Create: `database/seeders/ReportDataDemoSeeder.php` (local only — add to `.git/info/exclude`, do NOT commit)
- Modify: `resources/js/pages/panduan/content.js` (Sistem manual, Projek section: add group "Data laporan bulanan (Report Data)")

- [ ] **Step 1: Demo seeder**

Idempotent seeder for project PR4829 (first project) that creates: a main contract with particulars from the reference (RM 288,000,000; WJP 14,400,000; 24 months; DLP 12; LAD 52,128; possession 2025-10-31; completion 2027-10-31; CIDB "TBA"; one CAR insurance), 7 parties with contacts (from reference pages 5–6), org chart designations for existing members, baseline months Oct-25 → Oct-27 with the physical % series `0,1,1,2,3,5,7,10,16,25,34,43,52,60,69,76,81,84,87,91,94,97,98,99,100` and financial amounts from page 13, periods 1–3 (period 3 = 2025-12-16 → 2026-01-15, sched 2 / actual 4, fin 15 / 13, IPC No. 3), two delay notices (page 19), one test (page 33 RFI trial mix), worker categories (Management Team: Project Manager, Senior Engineer, Site Engineer, SHO, SSS, QAQC, Quantity Surveyor, Environmental Officer, Site Supervisor, Site Clerk/DC, General Foreman; Tradesman: General Worker, Operator, China Worker, Lorry Driver, Security Guard) and machinery categories (Excavator, Roller Compactor, Bulldozer, Jet Grouting Rig, Roro Bin, Welding Set, Genset, Tipper Truck, 3 Tonnes Lorry, Backpusher, Robin Engine, Lorry Crane). Print a summary line.

- [ ] **Step 2: Run it twice**

Run: `php artisan db:seed --class=ReportDataDemoSeeder` ×2 — counts must not double. `echo "database/seeders/ReportDataDemoSeeder.php" >> .git/info/exclude`.

- [ ] **Step 3: Panduan**

In `SISTEM_SECTIONS` → `projek` groups, append:
```js
{
    heading: 'Data laporan bulanan (tab Report Data)',
    steps: [
        'Buka projek, klik tab "Report Data".',
        'Contract Particulars — nilai kontrak, WJP, tempoh, DLP, tarikh milikan/siap, CIDB dan insurans (kontrak utama perlu ditanda "Main contract" di Projects > Contracts).',
        'Parties & Contacts — pihak dalam kontrak (Owner, SO, Consultant, Contractor, dll.) beserta alamat, logo dan pegawai untuk dihubungi.',
        'Organisation Chart — jawatan dan garis pelaporan ahli projek.',
        'Progress & Baseline — jadual CPM bulanan (scheduled %) dan rekod kemajuan setiap tempoh laporan; variance dan hari ahead/delay dikira automatik.',
        'Delay Notices & Tests — notis kelewatan dan rekod ujian/pentauliahan.',
        'Site-Log Categories — senarai kategori pekerja dan jentera khusus projek yang digunakan dalam borang Site Log.',
        'Report Images — peta lokasi, akses tapak dan gambar kemajuan (label seperti "Aerial 1" supaya boleh dibandingkan bulan ke bulan).',
    ],
},
```

- [ ] **Step 4: Full verification and final commit**

Run: `php vendor/bin/pint --test && php -d memory_limit=2G vendor/bin/phpunit && npm run build`
Expected: Pint pass; full suite OK (previous 336 + new ReportData/Unit tests); build success.

```bash
git add resources/js/pages/panduan/content.js public/build/manifest.json public/build/assets
git commit -m "Document the Report Data tab in the user guide"
git push
```

---

## Self-review

**Spec coverage (Phase A = spec §3.1–3.13, §6 supporting routes, §7 item 4):**
- §3.1 Contract particulars → Task 2 ✔ (incl. `is_main` from §11.2)
- §3.2 Parties/contacts/logos → Task 3 ✔
- §3.3 Org chart fields → Task 4 ✔
- §3.4 Baseline + periods + maths → Task 5 ✔
- §3.5 Claim columns → Task 6 ✔
- §3.6 Programme import → **Phase E, intentionally excluded**
- §3.7 Delay notices → Task 7 ✔
- §3.8 Correspondence groups/types → Task 6 ✔
- §3.9 Tests → Task 7 ✔
- §3.10 Drawings flag → Task 6 ✔
- §3.11 Resource categories → Task 8 ✔
- §3.12 Weather chart → no schema change; Phase B/C
- §3.13 Report images → Task 9 ✔
- §11.1 cutoff per project → Task 1 ✔
- Report Data tab (§7.4) → Tasks 10–13 ✔

**Placeholder scan:** none of the forbidden phrases; every code step has code; Tasks 11–13 describe UI behaviour in prose with exact service calls, field lists and states rather than full JSX (the panels are large and the ContractParticularsPanel in Task 10 is the concrete pattern to copy).

**Type consistency:** `reportDataService` names in Task 10 match the routes in Tasks 2–9; `ProjectProgressPeriod` fields used in Tasks 9 (`period_id` FK), 12 and 14 match Task 5; `ResourceCategoryService::namesFor(projectId, kind)` used in Task 8's controller and SiteLogController; `ReportPeriod::containing` used in Task 5's `suggest()`.
