# Monthly Progress Report — Phase B (Report Core, Editor, Portrait PDF) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A user picks a project and a period, the system generates a Monthly Progress Report draft from the Phase A data, the user edits every section in the browser, and exports a portrait-only PDF in the JPS layout.

**Architecture:** `monthly_reports` + `monthly_report_sections` store a snapshot per section: `data` (machine-owned, produced by one `SectionBuilder` class per section key) and `overrides` (user-owned, same shape, sparse). `MonthlyReportService` creates/regenerates/finalises; a `SectionRegistry` maps keys → builders; the editor and the PDF both consume `merged = data ⊕ overrides` produced by `SectionMerger`. PDF is DomPDF (already installed) with one Blade partial per section, A4 portrait only in this phase (landscape/charts/Gantt come in Phase C, Word in D, programme import in E).

**Tech Stack:** Laravel 12, MySQL, DomPDF (`barryvdh/laravel-dompdf`), PHPUnit; React 19 + React Router 7 + Tailwind 4 + Axios. No new Composer packages.

**Spec:** `docs/superpowers/specs/2026-09-17-monthly-progress-report-design.md` (§2, §3.14, §4, §5.1, §6, §7, §11)

## Global Constraints

- Everything nested under a project resolves through `project_id`; report routes gated by `permission:reports.view` (open/export) and `permission:reports.manage` (create/edit/finalise) — `reports.manage` is added to `RolePermissionSeeder`; supporting data stays on `projects.*`.
- Section keys are exactly: `cover`, `1.1`, `1.2`, `1.3`, `1.4`, `1.5`, `2.1`, `2.2`, `2.3`, `2.4`, `2.5`, `2.6`, `3.1`, `3.2`, `3.4`, `3.6`, `3.7`, `4.1`, `4.2`, `4.3`, `5.0`. Builders never write to domain tables. Every `data` payload carries `schema: 1`.
- `overrides` may only contain keys that exist in `data` (validated server-side), plus `_rows` replacements for table sections (see §Section data shapes).
- Finalised reports (`status = final`) reject every write except `reopen` (which needs `reports.manage`).
- PDF: A4 portrait, DejaVu Sans (already used), no remote assets, images embedded as `data:` URIs read from disk `local`; no MIME sniffing (production lacks `fileinfo`); `set_time_limit(120)` around export.
- Pint via `php vendor/bin/pint`; tests via `php artisan test tests/Feature/MonthlyReport` (full suite: `php -d memory_limit=2G vendor/bin/phpunit`); `npm run build` + commit hashed assets + manifest for any `resources/js` change.
- **Never run `migrate:fresh`, `migrate:refresh`, `migrate:rollback` or `db:wipe` on the local database.** Use `php artisan migrate` only.
- Commit after every task with `Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>`; do NOT push (phase rule).

---

## File structure

**Backend (create)**
- `database/migrations/2026_09_19_000001_create_monthly_reports_tables.php`
- `database/migrations/2026_09_19_000002_add_reports_manage_permission.php` (seeds the permission row)
- `app/Models/MonthlyReport.php`, `app/Models/MonthlyReportSection.php`, `app/Models/MonthlyReportAsset.php`
- `app/Services/MonthlyReport/ReportContext.php` — project, contract, parties, period, previousPeriod, options
- `app/Services/MonthlyReport/SectionBuilder.php` (interface), `SectionRegistry.php`, `SectionMerger.php`
- `app/Services/MonthlyReport/Sections/CoverBuilder.php`, `ProjectInformationBuilder.php` (1.1), `ContractCorrespondenceBuilder.php` (1.2), `ProjectLocationBuilder.php` (1.3), `OrganisationChartBuilder.php` (1.4), `ResourcePlanningBuilder.php` (1.5), `WorkProgressBuilder.php` (2.1), `PhysicalSCurveBuilder.php` (2.2), `ProgressClaimBuilder.php` (2.3), `FinancialSCurveBuilder.php` (2.4), `WorkProgrammeBuilder.php` (2.5), `DelayNoticeBuilder.php` (2.6), `DocumentSubmissionBuilder.php` (3.1), `PendingCorrespondenceBuilder.php` (3.2), `TestingBuilder.php` (3.4), `TenderDrawingBuilder.php` (3.6), `MeetingListBuilder.php` (3.7), `TradeWorkerBuilder.php` (4.1), `MachineryBuilder.php` (4.2), `WeatherBuilder.php` (4.3), `ProgressPhotoBuilder.php` (5.0)
- `app/Services/MonthlyReport/MonthlyReportService.php`
- `app/Services/MonthlyReport/Export/PdfExporter.php`
- `app/Http/Controllers/Api/MonthlyReportController.php`
- `resources/views/pdf/monthly-report/layout.blade.php`, `cover.blade.php`, `section.blade.php` (dispatcher), one partial per key under `resources/views/pdf/monthly-report/sections/` (`s1-1.blade.php` … `s5-0.blade.php`)
- `tests/Feature/MonthlyReport/*` (one file per task), `tests/Unit/MonthlyReport/SectionMergerTest.php`, `tests/Unit/MonthlyReport/WeatherBuilderTest.php`

**Backend (modify)**
- `routes/api.php`, `database/seeders/RolePermissionSeeder.php` (add `reports.manage` to the list)

**Frontend (create)**
- `resources/js/services/monthlyReportService.js`
- `resources/js/pages/reports/MonthlyReports.jsx` (list + New report dialog)
- `resources/js/pages/reports/MonthlyReportEditor.jsx` (shell: header, section nav, right pane)
- `resources/js/pages/reports/editor/ValueSection.jsx`, `TableSection.jsx`, `TextSection.jsx`, `ImageSection.jsx`, `SectionNotes.jsx`, `sectionConfig.js` (per-key field/column definitions used by ValueSection/TableSection)

**Frontend (modify)**
- `resources/js/app.jsx` (routes `/projects/monthly-reports`, `/projects/monthly-reports/:id`)
- `resources/js/layouts/DashboardLayout.jsx` (menu item under Projects: "Monthly Reports", permission `reports.view`)
- `resources/js/pages/panduan/content.js` (Sistem manual: new group)

---

## Section data shapes (the contract between builders, editor and PDF)

Every builder returns `['schema' => 1, ...]`. Editors edit **only** the parts listed as editable; everything else is display.

| Key | `data` shape (editable parts in **bold**) | Editor type |
|---|---|---|
| `cover` | `{report_no, report_no_words ("03 (TIGA)"), month_label, period_start, period_end, evaluation_date, **project_title**, contract_no, client:{name, address, logo}, so:{name, address, logo}, consultant:{name, address, logo}, contractor:{name, address, logo}, signatories:[{slot, **name**, **designation**, **company**}]}` | Value |
| `1.1` | `{rows:[{label, **value**}]}` — labels: Project Title, Contract Number, Contract Sum, Performance Guarantee Fund / WJP, Duration of Completion, LAD, Contract Period (Completion / DLP), Date Contract (Possession, Completion, DLP start, DLP end), CIDB Registration, Insurance (multi-line) | Table (fixed rows, value editable) |
| `1.2` | `{rows:[{party, **company**, **address**, **contacts:[{name, designation, tel, email}]**}]}` | Table |
| `1.3` | `{images:[{id, url, **caption**}]}` | Image |
| `1.4` | `{tree:[{name, designation, children:[…]}], **note**}` | Text (note only; tree display) |
| `1.5` | `{company, rows:[{designation, **nos**}]}` | Table |
| `2.1` | `{planning_days, physical:{prev_label, cur_label, rows:[{label, **prev**, **cur**}]}, financial:{…same}}` | Table (two fixed tables) |
| `2.2` | `{series:{months:[…], scheduled:[…], actual:[…]}, chart_asset_id}` — chart image comes in Phase C; PDF shows the table only in B | Text (note) |
| `2.3` | `{rows:[{ipc_no, submission_date, evaluation_date, claim_amount, certified, wjp_current, wjp_cumulative, paid_current, paid_cumulative, **remarks**}]}` | Table |
| `2.4` | like 2.2 with amounts | Text (note) |
| `2.5` | `{note:"Work programme import is available in a later phase.", **rows:[]**}` — placeholder in B | Table (free rows: no, task, duration, start, finish, actual, plan) |
| `2.6` | `{rows:[{no, **title**, **issue**, reg_number, submitted, reply, duration, status, **impact**}]}` | Table |
| `3.1` | `{groups:[{code, label, accumulative:{issued, open, closed}, previous:{…}, current:{…}}], previous_label, current_label}` | Table (numbers editable) |
| `3.2` | `{groups:[{code, label, ref_prefix, rows:[{no, reference, **title**, issued, approved, reminder, **status**}]}]}` | Table |
| `3.4` | `{rows:[{no, ref_no, name, date, result, **remarks**}]}` | Table |
| `3.6` | `{rows:[{no, drawing_no, title}]}` | Table |
| `3.7` | `{rows:[{no, **description**, date, **location**}]}` | Table |
| `4.1` | `{days:[{date, label, weekend}], groups:[{label, rows:[{no, description, counts:[…]}]}], totals:[…]}` | Table (counts editable) |
| `4.2` | `{days:[…], rows:[{no, description, counts:[…]}], totals:[…]}` | Table |
| `4.3` | `{days:[{date, intervals:[[startMin,endMin]]}], summary:{total_days, raining_days, raining_hours}}` | Text (note) |
| `5.0` | `{site_access:[{id,url,**caption**}], key_plan:[…], pairs:[{label, previous:{id,url}|null, current:{id,url}|null}]}` | Image |

`overrides` mirrors the shape sparsely. For tables, an override of `_rows` replaces the whole rows array (the editor always sends full rows); for value sections it is `{field: value}`; `notes` is a separate column.

---

### Task 1: Tables, models, permission, `SectionMerger`

**Files:**
- Create: `database/migrations/2026_09_19_000001_create_monthly_reports_tables.php`, `database/migrations/2026_09_19_000002_add_reports_manage_permission.php`, `app/Models/MonthlyReport.php`, `app/Models/MonthlyReportSection.php`, `app/Models/MonthlyReportAsset.php`, `app/Services/MonthlyReport/SectionMerger.php`
- Modify: `database/seeders/RolePermissionSeeder.php` (add `'reports.manage'` after `'reports.export'`)
- Test: `tests/Unit/MonthlyReport/SectionMergerTest.php`

**Interfaces:**
- Produces: models with relations `MonthlyReport::sections()` (ordered by `sort_order`), `assets()`, `project()`, `period()`; constants `MonthlyReport::STATUS_DRAFT = 'draft'`, `STATUS_FINAL = 'final'`; `SectionMerger::merge(array $data, ?array $overrides): array` (deep merge; `_rows` in overrides replaces `rows`; scalars override; unknown keys in overrides are ignored); `SectionMerger::validateOverrides(array $data, array $overrides): array` returns the list of unknown keys.

- [ ] **Step 1: Failing unit test**

```php
<?php

namespace Tests\Unit\MonthlyReport;

use App\Services\MonthlyReport\SectionMerger;
use PHPUnit\Framework\TestCase;

class SectionMergerTest extends TestCase
{
    public function test_scalars_and_nested_scalars_are_overridden(): void
    {
        $data = ['schema' => 1, 'project_title' => 'A', 'client' => ['name' => 'JPS', 'address' => 'X']];
        $merged = SectionMerger::merge($data, ['project_title' => 'B', 'client' => ['address' => 'Y']]);

        $this->assertSame('B', $merged['project_title']);
        $this->assertSame('JPS', $merged['client']['name']);
        $this->assertSame('Y', $merged['client']['address']);
    }

    public function test_rows_override_replaces_the_whole_array(): void
    {
        $data = ['schema' => 1, 'rows' => [['no' => 1, 'title' => 'a'], ['no' => 2, 'title' => 'b']]];
        $merged = SectionMerger::merge($data, ['_rows' => [['no' => 1, 'title' => 'z']]]);

        $this->assertCount(1, $merged['rows']);
        $this->assertSame('z', $merged['rows'][0]['title']);
    }

    public function test_unknown_keys_are_reported_and_ignored(): void
    {
        $data = ['schema' => 1, 'x' => 1];
        $this->assertSame(['y'], SectionMerger::validateOverrides($data, ['x' => 2, 'y' => 3]));
        $this->assertSame(['schema' => 1, 'x' => 2], SectionMerger::merge($data, ['x' => 2, 'y' => 3]));
    }

    public function test_null_overrides_return_data_unchanged(): void
    {
        $data = ['schema' => 1, 'x' => 1];
        $this->assertSame($data, SectionMerger::merge($data, null));
    }
}
```

- [ ] **Step 2: Run** — `php vendor/bin/phpunit tests/Unit/MonthlyReport/SectionMergerTest.php` — Expected: class not found.

- [ ] **Step 3: Implement the merger**

```php
<?php

namespace App\Services\MonthlyReport;

class SectionMerger
{
    public static function merge(array $data, ?array $overrides): array
    {
        if (! $overrides) {
            return $data;
        }

        if (array_key_exists('_rows', $overrides) && is_array($overrides['_rows'])) {
            $data['rows'] = array_values($overrides['_rows']);
            unset($overrides['_rows']);
        }

        foreach ($overrides as $key => $value) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            if (is_array($value) && is_array($data[$key]) && ! array_is_list($data[$key])) {
                $data[$key] = self::merge($data[$key], $value);
            } else {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /** @return array<int, string> unknown top-level keys */
    public static function validateOverrides(array $data, array $overrides): array
    {
        $unknown = [];
        foreach (array_keys($overrides) as $key) {
            if ($key === '_rows') {
                if (! array_key_exists('rows', $data)) {
                    $unknown[] = '_rows';
                }

                continue;
            }
            if (! array_key_exists($key, $data)) {
                $unknown[] = (string) $key;
            }
        }

        return $unknown;
    }
}
```

- [ ] **Step 4: Run unit test** — Expected: PASS (4).

- [ ] **Step 5: Migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('project_progress_periods')->cascadeOnDelete();
            $table->unsignedSmallInteger('report_no');
            $table->string('title');
            $table->string('month_label', 40);
            $table->date('evaluation_date')->nullable();
            $table->string('status', 10)->default('draft');
            $table->json('signatories')->nullable();
            $table->json('options')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalised_at')->nullable();
            $table->foreignId('finalised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'report_no']);
        });

        Schema::create('monthly_report_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('monthly_reports')->cascadeOnDelete();
            $table->string('key', 10);
            $table->string('title');
            $table->unsignedSmallInteger('sort_order');
            $table->boolean('include')->default(true);
            $table->json('data')->nullable();
            $table->json('overrides')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('regenerated_at')->nullable();
            $table->timestamps();
            $table->unique(['report_id', 'key']);
        });

        Schema::create('monthly_report_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('monthly_reports')->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_report_assets');
        Schema::dropIfExists('monthly_report_sections');
        Schema::dropIfExists('monthly_reports');
    }
};
```

Permission migration:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate('reports.manage', 'web');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'reports.manage')->where('guard_name', 'web')->delete();
    }
};
```

- [ ] **Step 6: Models**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyReport extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_FINAL = 'final';

    protected $fillable = ['project_id', 'period_id', 'report_no', 'title', 'month_label', 'evaluation_date', 'status', 'signatories', 'options', 'generated_at', 'generated_by', 'finalised_at', 'finalised_by'];

    protected function casts(): array
    {
        return ['evaluation_date' => 'date:Y-m-d', 'signatories' => 'array', 'options' => 'array', 'generated_at' => 'datetime', 'finalised_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(ProjectProgressPeriod::class, 'period_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(MonthlyReportSection::class, 'report_id')->orderBy('sort_order');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(MonthlyReportAsset::class, 'report_id')->orderBy('sort_order');
    }

    public function isFinal(): bool
    {
        return $this->status === self::STATUS_FINAL;
    }
}
```

```php
<?php

namespace App\Models;

use App\Services\MonthlyReport\SectionMerger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyReportSection extends Model
{
    protected $fillable = ['report_id', 'key', 'title', 'sort_order', 'include', 'data', 'overrides', 'notes', 'regenerated_at'];

    protected $appends = ['merged'];

    protected function casts(): array
    {
        return ['include' => 'boolean', 'data' => 'array', 'overrides' => 'array', 'regenerated_at' => 'datetime'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(MonthlyReport::class, 'report_id');
    }

    public function getMergedAttribute(): array
    {
        return SectionMerger::merge($this->data ?? ['schema' => 1], $this->overrides);
    }
}
```

`MonthlyReportAsset`: fillable `report_id, kind, file_path, file_name, sort_order`; `report()` belongsTo.

- [ ] **Step 7: Seeder line, migrate, lint, commit**

Add `'reports.manage',` after `'reports.export',` in `RolePermissionSeeder`. Run: `php artisan migrate && php vendor/bin/phpunit tests/Unit/MonthlyReport && php vendor/bin/pint --test`.

```bash
git add database/migrations/2026_09_19_000001_create_monthly_reports_tables.php database/migrations/2026_09_19_000002_add_reports_manage_permission.php app/Models/MonthlyReport.php app/Models/MonthlyReportSection.php app/Models/MonthlyReportAsset.php app/Services/MonthlyReport/SectionMerger.php database/seeders/RolePermissionSeeder.php tests/Unit/MonthlyReport/SectionMergerTest.php
git commit -m "Add monthly report tables, models, merger and reports.manage permission"
```

---

### Task 2: `ReportContext`, `SectionBuilder`, `SectionRegistry`, cover + 1.1 + 1.2 builders

**Files:**
- Create: `app/Services/MonthlyReport/ReportContext.php`, `SectionBuilder.php`, `SectionRegistry.php`, `Sections/CoverBuilder.php`, `Sections/ProjectInformationBuilder.php`, `Sections/ContractCorrespondenceBuilder.php`
- Test: `tests/Feature/MonthlyReport/ContractSectionsTest.php`

**Interfaces:**
- Produces:
  ```php
  final class ReportContext {
      public function __construct(
          public readonly Project $project,
          public readonly ?ProjectContract $contract,        // main contract or null
          public readonly ProjectProgressPeriod $period,
          public readonly ?ProjectProgressPeriod $previousPeriod,
          public readonly Collection $parties,               // ProjectParty with contacts, report_role not null, by sort_order
          public readonly int $reportNo,
          public readonly array $options = [],
      ) {}
      public static function for(MonthlyReport $report): self;   // loads everything
      public function party(string $role): ?ProjectParty;         // by report_role
      public function periodLabel(): string;                      // "15 DEC 2025 – 15 JAN 2026"
  }
  interface SectionBuilder { public static function key(): string; public static function title(): string; public function build(ReportContext $ctx): array; }
  final class SectionRegistry { /** @return array<string, class-string<SectionBuilder>> ordered */ public static function all(): array; public static function make(string $key): SectionBuilder; public static function titles(): array; }
  ```
- Registry order = the key list in Global Constraints; each builder's `title()` is the reference heading (e.g. `1.1 PROJECT INFORMATION`). Until later tasks add them, unimplemented keys map to `Sections\PlaceholderBuilder` (returns `['schema'=>1,'placeholder'=>true]`) so Task 6 can generate a full report from the start; later tasks replace the mapping.

- [ ] **Step 1: Failing test**

```php
<?php

namespace Tests\Feature\MonthlyReport;

use App\Models\MonthlyReport;
use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\ProjectParty;
use App\Models\ProjectProgressPeriod;
use App\Services\MonthlyReport\ReportContext;
use App\Services\MonthlyReport\SectionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractSectionsTest extends TestCase
{
    use RefreshDatabase;

    private function context(): ReportContext
    {
        $project = Project::create(['name' => 'RTB SG. MUAR', 'code' => 'JPS/IP/BPB/10/2025', 'status' => 'in_progress']);
        ProjectContract::create(['project_id' => $project->id, 'title' => 'Main', 'contract_no' => 'JPS/IP/BPB/10/2025', 'is_main' => true,
            'contract_sum' => 288000000, 'performance_bond_amount' => 14400000, 'duration_months' => 24, 'dlp_months' => 12, 'lad_per_day' => 52128,
            'possession_date' => '2025-10-31', 'completion_date' => '2027-10-31', 'dlp_start_date' => '2027-11-01', 'dlp_end_date' => '2028-11-01', 'cidb_registration' => 'TBA',
            'insurances' => [['type' => "Contractor's All Risk", 'insurer' => 'PACIFIC INSURANCE', 'policy_no' => 'CEC-E0039188-H1', 'period_from' => '2025-10-31', 'period_to' => '2027-10-31', 'maintenance_from' => '2027-11-01', 'maintenance_to' => '2029-02-12']]]);
        $owner = ProjectParty::create(['project_id' => $project->id, 'name' => 'BAHAGIAN PENGURUSAN BANJIR, JPS MALAYSIA', 'type' => 'client', 'report_role' => 'owner', 'address' => 'Aras 3, Blok A, Cyber 8', 'sort_order' => 0]);
        $owner->contacts()->create(['name' => 'Ir. Marenawati binti Abd Malek', 'email' => 'marenawati@water.gov.my']);
        ProjectParty::create(['project_id' => $project->id, 'name' => 'MULTI GREEN ENGINEERING SDN BHD', 'type' => 'main_contractor', 'report_role' => 'contractor', 'address' => 'No. 35, Segamat', 'sort_order' => 6]);
        $period = ProjectProgressPeriod::create(['project_id' => $project->id, 'period_no' => 3, 'period_start' => '2025-12-16', 'period_end' => '2026-01-15']);
        $report = MonthlyReport::create(['project_id' => $project->id, 'period_id' => $period->id, 'report_no' => 3, 'title' => 'Monthly Progress Report No.3', 'month_label' => 'January 2026']);

        return ReportContext::for($report);
    }

    public function test_cover_builder_collects_parties_and_report_meta(): void
    {
        $data = SectionRegistry::make('cover')->build($this->context());

        $this->assertSame(1, $data['schema']);
        $this->assertSame('03 (TIGA)', $data['report_no_words']);
        $this->assertSame('MULTI GREEN ENGINEERING SDN BHD', $data['contractor']['name']);
        $this->assertSame('BAHAGIAN PENGURUSAN BANJIR, JPS MALAYSIA', $data['client']['name']);
        $this->assertNull($data['consultant']);
        $this->assertSame('15 DEC 2025 – 15 JAN 2026', $data['period_label']);
        $this->assertCount(3, $data['signatories']);
    }

    public function test_project_information_rows_are_rendered_from_contract_particulars(): void
    {
        $rows = collect(SectionRegistry::make('1.1')->build($this->context())['rows'])->keyBy('label');

        $this->assertSame('RM 288,000,000.00 (Two Hundred Eighty-Eight Million Ringgit Only)', $rows['Contract Sum']['value']);
        $this->assertSame('24 MONTHS', $rows['Duration of Completion']['value']);
        $this->assertSame('RM 52,128.00/day', $rows['LAD']['value']);
        $this->assertStringContainsString('Possession Date: 31/10/2025', $rows['Date Contract']['value']);
        $this->assertStringContainsString('PACIFIC INSURANCE', $rows['Insurance']['value']);
    }

    public function test_contract_correspondence_lists_parties_with_contacts(): void
    {
        $data = SectionRegistry::make('1.2')->build($this->context());

        $this->assertSame('Project Owner', $data['rows'][0]['party']);
        $this->assertSame('marenawati@water.gov.my', $data['rows'][0]['contacts'][0]['email']);
        $this->assertSame('Contractor', $data['rows'][1]['party']);
    }

    public function test_registry_lists_every_key_in_order(): void
    {
        $this->assertSame(['cover', '1.1', '1.2', '1.3', '1.4', '1.5', '2.1', '2.2', '2.3', '2.4', '2.5', '2.6', '3.1', '3.2', '3.4', '3.6', '3.7', '4.1', '4.2', '4.3', '5.0'], array_keys(SectionRegistry::all()));
    }
}
```

- [ ] **Step 2: Run** — Expected: class not found.

- [ ] **Step 3: Implement**

`ReportContext`:
```php
<?php

namespace App\Services\MonthlyReport;

use App\Models\MonthlyReport;
use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\ProjectParty;
use App\Models\ProjectProgressPeriod;
use Illuminate\Support\Collection;

final class ReportContext
{
    public function __construct(
        public readonly Project $project,
        public readonly ?ProjectContract $contract,
        public readonly ProjectProgressPeriod $period,
        public readonly ?ProjectProgressPeriod $previousPeriod,
        public readonly Collection $parties,
        public readonly int $reportNo,
        public readonly array $options = [],
    ) {}

    public static function for(MonthlyReport $report): self
    {
        $project = $report->project()->with('client')->firstOrFail();
        $period = $report->period()->firstOrFail();

        return new self(
            project: $project,
            contract: ProjectContract::where('project_id', $project->id)->where('is_main', true)->first(),
            period: $period,
            previousPeriod: ProjectProgressPeriod::where('project_id', $project->id)->where('period_no', '<', $period->period_no)->orderByDesc('period_no')->first(),
            parties: ProjectParty::where('project_id', $project->id)->whereNotNull('report_role')->with('contacts')->orderBy('sort_order')->get(),
            reportNo: (int) $report->report_no,
            options: $report->options ?? [],
        );
    }

    public function party(string $role): ?ProjectParty
    {
        return $this->parties->firstWhere('report_role', $role);
    }

    public function periodLabel(): string
    {
        return strtoupper($this->period->period_start->format('d M Y')).' – '.strtoupper($this->period->period_end->format('d M Y'));
    }
}
```

`SectionBuilder` interface and `SectionRegistry`:
```php
<?php

namespace App\Services\MonthlyReport;

interface SectionBuilder
{
    public static function key(): string;

    public static function title(): string;

    /** @return array<string, mixed> with 'schema' => 1 */
    public function build(ReportContext $ctx): array;
}
```
```php
<?php

namespace App\Services\MonthlyReport;

use App\Services\MonthlyReport\Sections as S;
use InvalidArgumentException;

final class SectionRegistry
{
    /** @return array<string, class-string<SectionBuilder>> */
    public static function all(): array
    {
        return [
            'cover' => S\CoverBuilder::class,
            '1.1' => S\ProjectInformationBuilder::class,
            '1.2' => S\ContractCorrespondenceBuilder::class,
            '1.3' => S\PlaceholderBuilder::class,
            '1.4' => S\PlaceholderBuilder::class,
            '1.5' => S\PlaceholderBuilder::class,
            '2.1' => S\PlaceholderBuilder::class,
            '2.2' => S\PlaceholderBuilder::class,
            '2.3' => S\PlaceholderBuilder::class,
            '2.4' => S\PlaceholderBuilder::class,
            '2.5' => S\PlaceholderBuilder::class,
            '2.6' => S\PlaceholderBuilder::class,
            '3.1' => S\PlaceholderBuilder::class,
            '3.2' => S\PlaceholderBuilder::class,
            '3.4' => S\PlaceholderBuilder::class,
            '3.6' => S\PlaceholderBuilder::class,
            '3.7' => S\PlaceholderBuilder::class,
            '4.1' => S\PlaceholderBuilder::class,
            '4.2' => S\PlaceholderBuilder::class,
            '4.3' => S\PlaceholderBuilder::class,
            '5.0' => S\PlaceholderBuilder::class,
        ];
    }

    public const TITLES = [
        'cover' => 'Cover & Signatories',
        '1.1' => '1.1 PROJECT INFORMATION',
        '1.2' => '1.2 CONTRACT CORRESPONDENCE',
        '1.3' => '1.3 PROJECT LOCATION',
        '1.4' => '1.4 ORGANISATION CHART',
        '1.5' => '1.5 RESOURCE PLANNING',
        '2.1' => '2.1 SUMMARY WORK PROGRESS',
        '2.2' => '2.2 PHYSICAL S-CURVE',
        '2.3' => '2.3 SUMMARY OF PROGRESS CLAIM',
        '2.4' => '2.4 FINANCIAL S-CURVE',
        '2.5' => '2.5 ACTUAL WORK PROGRESS',
        '2.6' => '2.6 NOTICE OF DELAY',
        '3.1' => '3.1 SUMMARY OF DOCUMENTS SUBMISSION',
        '3.2' => '3.2 LIST OF PENDING CORRESPONDENCE',
        '3.4' => '3.4 LIST OF TESTING AND COMMISSIONING',
        '3.6' => '3.6 LIST OF TENDER DRAWING',
        '3.7' => '3.7 LIST OF MEETING / DISCUSSION',
        '4.1' => '4.1 MONTHLY TRADE WORKER',
        '4.2' => '4.2 MONTHLY MACHINERIES & PLANT',
        '4.3' => '4.3 WEATHER REPORT',
        '5.0' => '5.0 PROGRESS PHOTOGRAPH',
    ];

    public static function make(string $key): SectionBuilder
    {
        $class = self::all()[$key] ?? throw new InvalidArgumentException("Unknown section {$key}");

        return new $class($key);
    }

    public static function titles(): array
    {
        return self::TITLES;
    }
}
```
`PlaceholderBuilder` (constructor takes `$key`; `key()`/`title()` static return from a static property set per instance is awkward — implement `key()`/`title()` as **instance** methods instead: change the interface to `public function key(): string; public function title(): string;` and have every builder accept `string $key` in its constructor and return `SectionRegistry::TITLES[$this->key]`). Use a small abstract `AbstractBuilder` with the constructor + those two methods; concrete builders extend it and implement `build()`.

```php
<?php

namespace App\Services\MonthlyReport\Sections;

use App\Services\MonthlyReport\ReportContext;
use App\Services\MonthlyReport\SectionBuilder;
use App\Services\MonthlyReport\SectionRegistry;

abstract class AbstractBuilder implements SectionBuilder
{
    public function __construct(protected readonly string $key) {}

    public function key(): string
    {
        return $this->key;
    }

    public function title(): string
    {
        return SectionRegistry::TITLES[$this->key];
    }

    abstract public function build(ReportContext $ctx): array;

    protected function money(?float $amount): ?string
    {
        return $amount === null ? null : 'RM '.number_format($amount, 2);
    }
}

final class PlaceholderBuilder extends AbstractBuilder
{
    public function build(ReportContext $ctx): array
    {
        return ['schema' => 1, 'placeholder' => true];
    }
}
```
(Put `PlaceholderBuilder` in its own file.)

`CoverBuilder`:
```php
<?php

namespace App\Services\MonthlyReport\Sections;

use App\Models\ProjectParty;
use App\Services\MonthlyReport\ReportContext;

final class CoverBuilder extends AbstractBuilder
{
    private const WORDS = [1 => 'SATU', 'DUA', 'TIGA', 'EMPAT', 'LIMA', 'ENAM', 'TUJUH', 'LAPAN', 'SEMBILAN', 'SEPULUH', 'SEBELAS', 'DUA BELAS'];

    public function build(ReportContext $ctx): array
    {
        $party = fn (string $role) => ($p = $ctx->party($role)) ? $this->party($p) : null;
        $contractor = $ctx->party('contractor');

        return [
            'schema' => 1,
            'report_no' => $ctx->reportNo,
            'report_no_words' => sprintf('%02d (%s)', $ctx->reportNo, self::WORDS[$ctx->reportNo] ?? (string) $ctx->reportNo),
            'period_label' => $ctx->periodLabel(),
            'period_start' => $ctx->period->period_start->toDateString(),
            'period_end' => $ctx->period->period_end->toDateString(),
            'project_title' => $ctx->project->name,
            'contract_no' => $ctx->contract?->contract_no ?? $ctx->project->code,
            'client' => $party('owner'),
            'so' => $party('superintending_officer'),
            'consultant' => $party('consultant'),
            'contractor' => $party('contractor'),
            'signatories' => [
                ['slot' => 'prepared', 'name' => $contractor?->contacts->first()?->name ?? '', 'designation' => $contractor?->contacts->first()?->designation ?? 'Project Manager', 'company' => $contractor?->name ?? ''],
                ['slot' => 'verified', 'name' => $ctx->party('consultant')?->contacts->first()?->name ?? '', 'designation' => '', 'company' => $ctx->party('consultant')?->name ?? ''],
                ['slot' => 'accepted', 'name' => '', 'designation' => '', 'company' => $ctx->party('superintending_officer')?->name ?? ''],
            ],
        ];
    }

    private function party(ProjectParty $p): array
    {
        return ['id' => $p->id, 'name' => $p->name, 'address' => $p->address, 'has_logo' => (bool) $p->logo_path];
    }
}
```

`ProjectInformationBuilder`:
```php
<?php

namespace App\Services\MonthlyReport\Sections;

use App\Services\MonthlyReport\ReportContext;
use App\Support\RinggitWords;
use Carbon\Carbon;

final class ProjectInformationBuilder extends AbstractBuilder
{
    public function build(ReportContext $ctx): array
    {
        $c = $ctx->contract;
        $d = fn ($v) => $v ? Carbon::parse($v)->format('d/m/Y') : '-';
        $rows = [
            ['label' => 'Project Title', 'value' => $ctx->project->name],
            ['label' => 'Contract Number', 'value' => $c?->contract_no ?? $ctx->project->code],
            ['label' => 'Contract Sum', 'value' => $c?->contract_sum !== null ? $this->money((float) $c->contract_sum).' ('.RinggitWords::spell($c->contract_sum).')' : '-'],
            ['label' => 'Performance Guarantee Fund / WJP', 'value' => $c?->performance_bond_amount !== null ? $this->money((float) $c->performance_bond_amount).' ('.RinggitWords::spell($c->performance_bond_amount).')' : '-'],
            ['label' => 'Duration of Completion', 'value' => $c?->duration_months ? $c->duration_months.' MONTHS' : '-'],
            ['label' => 'LAD', 'value' => $c?->lad_per_day !== null ? $this->money((float) $c->lad_per_day).'/day' : '-'],
            ['label' => 'Contract Period', 'value' => 'COMPLETION: '.($c?->duration_months ? $c->duration_months.' MONTHS' : '-')."\nDLP: ".($c?->dlp_months ? $c->dlp_months.' MONTHS' : '-')],
            ['label' => 'Date Contract', 'value' => 'Possession Date: '.$d($c?->possession_date)."\nCompletion Date: ".$d($c?->completion_date)."\nDLP Start Date: ".$d($c?->dlp_start_date)."\nDLP Completion Date: ".$d($c?->dlp_end_date)],
            ['label' => 'CIDB Registration', 'value' => $c?->cidb_registration ?: 'TBA'],
            ['label' => 'Insurance', 'value' => $this->insurances($c?->insurances ?? [])],
        ];

        return ['schema' => 1, 'rows' => $rows];
    }

    private function insurances(array $list): string
    {
        if ($list === []) {
            return '-';
        }
        $d = fn ($v) => $v ? Carbon::parse($v)->format('d/m/Y') : '-';
        $lines = [];
        foreach ($list as $i) {
            $lines[] = ($i['type'] ?? 'Insurance').' : '.($i['insurer'] ?? '-')
                ."\nPolicy Number : ".($i['policy_no'] ?? '-')
                ."\nPeriod of Insurance : ".$d($i['period_from'] ?? null).' until '.$d($i['period_to'] ?? null)
                ."\nMaintenance Period : ".$d($i['maintenance_from'] ?? null).' until '.$d($i['maintenance_to'] ?? null);
        }

        return implode("\n\n", $lines);
    }
}
```

`ContractCorrespondenceBuilder`:
```php
<?php

namespace App\Services\MonthlyReport\Sections;

use App\Models\ProjectParty;
use App\Services\MonthlyReport\ReportContext;

final class ContractCorrespondenceBuilder extends AbstractBuilder
{
    public const ROLE_LABELS = [
        'owner' => 'Project Owner', 'superintending_officer' => 'Superintendent Officer (SO)', 'so_representative' => 'Representative of the Principal Superintending Officer',
        'district_engineer' => 'District Engineer', 'quantity_surveyor' => 'Quantity Surveyor', 'consultant' => 'Consultant', 'contractor' => 'Contractor',
    ];

    public function build(ReportContext $ctx): array
    {
        $rows = $ctx->parties->map(fn (ProjectParty $p) => [
            'party' => $p->report_role === 'other' ? ($p->role_label ?: 'Other') : (self::ROLE_LABELS[$p->report_role] ?? $p->report_role),
            'company' => $p->name,
            'address' => $p->address,
            'contacts' => $p->contacts->map(fn ($c) => ['name' => $c->name, 'designation' => $c->designation, 'tel' => $c->phone, 'email' => $c->email])->values()->all(),
        ])->values()->all();

        return ['schema' => 1, 'rows' => $rows];
    }
}
```

- [ ] **Step 4: Run** — `php artisan test tests/Feature/MonthlyReport/ContractSectionsTest.php` — Expected: PASS (4).

- [ ] **Step 5: Lint, commit**

```bash
git add app/Services/MonthlyReport tests/Feature/MonthlyReport/ContractSectionsTest.php
git commit -m "Add report context, section registry and contract section builders"
```

---

### Task 3: Progress builders — 1.4, 1.5, 2.1, 2.2, 2.3, 2.4

**Files:**
- Create: `Sections/OrganisationChartBuilder.php`, `ResourcePlanningBuilder.php`, `WorkProgressBuilder.php`, `PhysicalSCurveBuilder.php`, `ProgressClaimBuilder.php`, `FinancialSCurveBuilder.php`
- Modify: `SectionRegistry.php` (map the six keys)
- Test: `tests/Feature/MonthlyReport/ProgressSectionsTest.php`

**Interfaces:** shapes from the table above. Details:
- 1.4: tree from `project_members` (active, `designation`, `reports_to_user_id`, `org_sort`) — same algorithm as `OrgChartController::show()` (orphans at top level, visited guard). Data: `['schema'=>1, 'company'=>contractor name, 'tree'=>[...], 'note'=>'']`.
- 1.5: rows = active members grouped by `designation` (null → 'Unassigned') with `nos` count, ordered by first `org_sort`. `company` = contractor party name or config('payroll.company.name').
- 2.1: `planning_days` = period.planning_days_completion; `physical.rows` labels `Scheduled Progress`, `Actual Progress`, `Variance (+ / -)`, `Ahead /Delay in Day`, `Status` with `prev` from previousPeriod (or '-') and `cur` from period; percentages formatted `"2%"` (`round` to 0 dp when integer, else 1 dp), variance with sign `"+2%"`, days `"10 Days"`; `financial.rows` labels `Scheduled Progress`, `Actual Progress`, `Variance (+ / -)`, `Status` (`financial_status` or 'No Claim'). Labels: `prev_label` = `"Previous (".prev.period_end d M Y.")"`, `cur_label` similar.
- 2.2: `series.months` = baseline months as `'M-y'` (`Oct-25`), `scheduled` = baseline `scheduled_physical_pct`, `actual` = for each month the `physical_actual_pct` of the period whose `period_end` falls in that month (null otherwise). `chart_asset_id` null in B.
- 2.3: rows from `ProjectInvoice` where `type='client'` for the project ordered by `claim_number` then `invoice_date`: `ipc_no`=claim_number, `submission_date`=invoice_date d/m/Y, `evaluation_date`, `claim_amount`=amount, `certified`=certified_current ?? payment cert amount fallback `amount` when `payment_cert_date` set else 0, `wjp_current`, `wjp_cumulative`, `paid_current`=certified_current, `paid_cumulative`=certified_cumulative, `remarks`=status label. Numbers as floats (the PDF formats).
- 2.4: like 2.2 with `scheduled` = `scheduled_financial_amount`, `scheduled_pct`, `actual` = period `financial_actual_amount`, `actual_pct`.

- [ ] **Step 1: Failing test** — build a project with 3 members in a chain, a baseline of 4 months (Oct-25..Jan-26 with 0,1,1,2 %), periods 2 (end 2025-12-15, sched 1 actual 1) and 3 (end 2026-01-15, sched 2 actual 4, planning 497), two client invoices (claim 1, 2). Assert: `1.4` tree root is the PM with one child; `1.5` rows contain `['designation' => 'Site Supervisor', 'nos' => 2]` when two supervisors exist; `2.1` physical rows: Scheduled prev `1%` cur `2%`, Actual `1%`/`4%`, Variance `0%`/`+2%`, Ahead/Delay `0`/`10 Days`, Status `ON TRACK`/`AHEAD`; `2.2` series months `['Oct-25','Nov-25','Dec-25','Jan-26']`, actual `[null,null,1.0,4.0]`; `2.3` two rows with `ipc_no` 1 and 2; `2.4` scheduled amounts array length 4.

- [ ] **Step 2: Run** — FAIL. **Step 3: Implement the six builders** per the specification above (each its own file, extending `AbstractBuilder`; reuse `ProgressService::compute` semantics only for formatting, never recompute stored values). **Step 4: Run** — PASS. **Step 5: Map keys in `SectionRegistry::all()`, Pint, commit** `"Add organisation, progress and claim section builders"`.

---

### Task 4: Register builders — 2.5 placeholder, 2.6, 3.1, 3.2, 3.4, 3.6, 3.7

**Files:**
- Create: `Sections/WorkProgrammeBuilder.php`, `DelayNoticeBuilder.php`, `DocumentSubmissionBuilder.php`, `PendingCorrespondenceBuilder.php`, `TestingBuilder.php`, `TenderDrawingBuilder.php`, `MeetingListBuilder.php`
- Modify: `SectionRegistry.php`
- Test: `tests/Feature/MonthlyReport/RegisterSectionsTest.php`

**Interfaces:**
- 2.5: `['schema'=>1,'note'=>'Work programme import is available in a later phase.','rows'=>[]]`.
- 2.6: rows from `ProjectDelayNotice` (project, by sort_order, submitted_date): `no`, `title`, `issue`, `reg_number`, `submitted` (d.m.Y + `"\nvia ".submitted_via` when set), `reply` (d.m.Y or '-'), `duration` (duration_days), `status` ('Close'|'Open'), `impact`.
- 3.1: `CorrespondenceType` with `report_group` not null ordered by sort_order → groups `code`, `label` (`full_name`); counts over `project_correspondences` of that type for the project: accumulative = `date <= period_end`, previous = `date <= previousPeriod.period_end` (0 when none), current = `date` within the period; `issued` = count, `closed` = status `closed` (use the column the Correspondence module uses — check `ProjectCorrespondence` for `status`/`closed_at`/date field names and use them), `open` = issued − closed. `previous_label` = previous period_end d/m/Y or '-', `current_label` = period_end d/m/Y.
- 3.2: same groups; rows = correspondences with status not closed, ordered by date: `no`, `reference` (reference_no), `title` (subject), `issued` (d/m/Y), `approved` ('-' unless closed date), `reminder` ('-'), `status` (upper-cased status label). `ref_prefix` = `"MGE/{client code}/".strtoupper(code)."/(No)"` — client code = first token of project code before `/` if any else project code.
- 3.4: rows from `ProjectTest`: `no`, `ref_no`, `name`, `date` (d/m/Y), `result`, `remarks`.
- 3.6: rows from `Drawing` where `project_id` and `is_tender` ordered by `drawing_no`: `no`, `drawing_no`, `title`.
- 3.7: rows from `MeetingMinute` where `project_id` ordered by `meeting_date`: `no`, `description` (title), `date` (d.m.Y), `location`.

- [ ] **Step 1: Failing test** — seed: 2 delay notices, correspondence types adm/rfi with 3 ADM correspondences (2 before period end, 1 inside; one closed), 1 test, 2 drawings (one tender), 2 meetings. Assert 2.6 has 2 rows with `duration` ints; 3.1 ADM group accumulative issued 3 / closed 1 / open 2 and current issued 1; 3.2 ADM rows exclude the closed one; 3.6 lists only the tender drawing; 3.7 lists 2 rows ordered by date. **Step 2: Run** — FAIL. **Step 3: Implement** (read `app/Models/ProjectCorrespondence.php` and the Correspondence migrations for the exact date/status/reference column names before coding 3.1/3.2). **Step 4: PASS. Step 5: Map keys, Pint, commit** `"Add correspondence, register and drawing section builders"`.

---

### Task 5: Site-log builders — 4.1, 4.2, 4.3 — and image builders 1.3, 5.0

**Files:**
- Create: `Sections/TradeWorkerBuilder.php`, `MachineryBuilder.php`, `WeatherBuilder.php`, `ProjectLocationBuilder.php`, `ProgressPhotoBuilder.php`
- Modify: `SectionRegistry.php`
- Test: `tests/Unit/MonthlyReport/WeatherBuilderTest.php`, `tests/Feature/MonthlyReport/SiteLogSectionsTest.php`

**Interfaces:**
- Shared day axis: `days` = every calendar date from period_start to period_end: `['date'=>'2025-12-16','label'=>'16','month'=>'Dec-25','weekend'=>bool]`; the PDF/editor group consecutive days by `month` for the two-row header.
- 4.1: categories from `ProjectResourceCategory` kind worker (project list, else defaults with group 'Tradesman'); `groups` = distinct `group` in order (null → 'Tradesman'); each row `counts[i]` = sum of `site_log_workers.count` for that `worker_type` on `days[i]` across the project's site logs (null when no site log that day → rendered '-'); `totals[i]` = column sum. Include a row for any worker_type that appears in logs but not in the category list (appended under 'Tradesman').
- 4.2: same over `site_log_machinery.quantity` by `machinery_type` (no groups).
- 4.3: `days[i].intervals` from `site_log_weather_events` for that date: walk events ordered by time; `rain_start` opens an interval, `rain_stop` closes it; an unclosed interval closes at 24:00; `rain_stop` without a start is ignored; minutes since midnight. `summary`: `total_days` = count(days), `raining_days` = days with ≥1 interval, `raining_hours` = round(sum(minutes)/60, 1).
- 1.3: `images` = `ReportImage` section `location` for the project ordered by sort_order → `{id, url:"/api/projects/{p}/report-images/{id}/view", caption}`.
- 5.0: `site_access` (section `site_access`), `key_plan` (`progress_key_plan`), `pairs` = for each distinct `label` among section `progress` images with `period_id` ∈ {period, previousPeriod}: `previous` = image for previousPeriod, `current` = image for period; ordered by label with 'Overall Site View' first when present.

Weather unit test (pure function): put the interval maths in `public static function intervals(array $events): array` on `WeatherBuilder` where `$events = [['condition'=>'rain_start','time'=>'09:30'], ...]` and test: single interval 09:30–11:00 → `[[570,660]]`; start without stop → `[[570,1440]]`; stop without start → `[]`; two intervals summed correctly; summary hours for one day with 90 min = 1.5.

- [ ] **Step 1: Failing tests** (unit + feature: a period 2025-12-16..2025-12-18, two site logs with workers 'General Worker' 30 / 'Project Manager' 1, machinery 'Excavator' 5, weather events one rainy day; two progress images labelled 'Aerial 1' for previous and current periods; one location image). Assert 4.1 has 3 days, `groups` include 'Management Team' when the category list is seeded that way, 'General Worker' counts `[30, null, 30]`-style; 4.2 Excavator `[5,…]`; 4.3 `raining_days = 1`; 5.0 `pairs[0]['label'] === 'Aerial 1'` with both sides set; 1.3 one image.
- [ ] **Step 2: FAIL. Step 3: Implement. Step 4: PASS. Step 5: Map keys, Pint, commit** `"Add site-log, weather and photograph section builders"`.

---

### Task 6: `MonthlyReportService` + API (create, show, list, update meta, section overrides/notes/include, regenerate, finalise/reopen, delete)

**Files:**
- Create: `app/Services/MonthlyReport/MonthlyReportService.php`, `app/Http/Controllers/Api/MonthlyReportController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/MonthlyReport/MonthlyReportApiTest.php`

**Interfaces:**
```php
final class MonthlyReportService {
    public function create(int $projectId, array $attrs, int $userId): MonthlyReport;   // attrs: report_no, period_id | (period_start, period_end → creates/uses a period), month_label?, evaluation_date?, copy_from_report_id?
    public function regenerate(MonthlyReport $r, ?string $key = null): MonthlyReport;   // all sections or one; keeps overrides/notes/include
    public function saveSection(MonthlyReport $r, string $key, array $payload): MonthlyReportSection; // payload keys: overrides?, notes?, include?
    public function finalise(MonthlyReport $r, int $userId): MonthlyReport;
    public function reopen(MonthlyReport $r): MonthlyReport;
    public function duplicateStatic(MonthlyReport $from, MonthlyReport $to): void;     // copies overrides+notes for 'cover','1.2','1.3','1.4','1.5','3.6' when copy_from_report_id given
}
```
Routes (group `Route::prefix('monthly-reports')` + nested project list/create):
```
GET    /api/monthly-reports                          reports.view   (?project_id, ?status, paginated, with project:id,name,code + period)
GET    /api/projects/{project}/monthly-reports       reports.view
POST   /api/projects/{project}/monthly-reports       reports.manage
GET    /api/monthly-reports/{report}                 reports.view   (report + sections with merged + project + period)
PUT    /api/monthly-reports/{report}                 reports.manage (title, month_label, evaluation_date, signatories, options)
PUT    /api/monthly-reports/{report}/sections/{key}  reports.manage
POST   /api/monthly-reports/{report}/regenerate      reports.manage (?key)
POST   /api/monthly-reports/{report}/finalise        reports.manage
POST   /api/monthly-reports/{report}/reopen          reports.manage
DELETE /api/monthly-reports/{report}                 reports.manage (draft only)
GET    /api/monthly-reports/{report}/export/pdf      reports.view   (Task 7)
```
- `create()`: `report_no` default = max+1; `title` = `"Monthly Progress Report No.{n}"`; `month_label` default = period_end `F Y`; creates one `MonthlyReportSection` per registry key with `sort_order` = index, `title` from `SectionRegistry::TITLES`, `data` from the builder, `include` true except `2.5` (false in Phase B); sets `generated_at/by`. Wrapped in a transaction. If `period_start/period_end` are given without `period_id`, create a `ProjectProgressPeriod` via the existing suggest logic (call `ProgressService` for defaults, compute, save).
- `saveSection()`: 422 when report is final; validates `overrides` with `SectionMerger::validateOverrides` (422 listing unknown keys); `notes` string nullable; `include` boolean.
- `regenerate()`: rebuilds `data` (and `title`) for the key(s), sets `regenerated_at`, keeps `overrides`; returns the report with sections.
- `finalise()`: requires all `include` sections to have data; sets status/final timestamps. `reopen()` sets draft. `delete` refuses final (422).
- Response shape for `show`: `{report..., project:{id,name,code}, period:{...}, sections:[{id,key,title,sort_order,include,data,overrides,notes,merged,regenerated_at}]}`.

- [ ] **Step 1: Failing feature test** — permissions `reports.view`, `reports.manage` (and `projects.view` for setup); seed a project with a main contract, one party, a period; then: (a) POST create → 201, 21 sections, `2.5.include === false`, `cover.merged.report_no_words === '01 (SATU)'`; (b) PUT section `1.1` with `overrides: {_rows: [...]}` → merged rows reflect it, `data` unchanged; PUT with unknown key `foo` → 422; (c) POST regenerate?key=1.1 → `data.rows` rebuilt, overrides kept; (d) POST finalise → status final; PUT section → 422; DELETE → 422; POST reopen → draft; DELETE → 200; (e) user with only `reports.view` → 403 on POST create; (f) GET list filtered by project.
- [ ] **Step 2: FAIL. Step 3: Implement service, controller, routes. Step 4: PASS. Step 5: Pint, commit** `"Add monthly report service and API"`.

---

### Task 7: Portrait PDF export

**Files:**
- Create: `app/Services/MonthlyReport/Export/PdfExporter.php`, `resources/views/pdf/monthly-report/layout.blade.php`, `resources/views/pdf/monthly-report/cover.blade.php`, `resources/views/pdf/monthly-report/section.blade.php`, `resources/views/pdf/monthly-report/sections/s1-1.blade.php` … `s5-0.blade.php` (17 partials; `s2-2`, `s2-4` render the series as a table with a note "Chart available in a later phase"; `s2-5` renders rows or the note)
- Modify: `MonthlyReportController.php` (`exportPdf`), `routes/api.php`
- Test: `tests/Feature/MonthlyReport/PdfExportTest.php`

**Interfaces:**
- `PdfExporter::render(MonthlyReport $report): \Barryvdh\DomPDF\PDF` — builds `$view = ['report'=>…, 'ctx'=>ReportContext, 'sections'=>[key => merged], 'notes'=>[key=>notes], 'logos'=>[role => data-uri|null], 'mgeLogo'=>data-uri|null]`, `Pdf::loadView('pdf.monthly-report.layout', $view)->setPaper('a4', 'portrait')`.
- Layout: `@page { margin: 22mm 16mm 20mm 16mm }`; fixed header (client logo left 60px, project title + `CONTRACT NO. : …` centred 9px uppercase, MGE logo right) and footer (`Page X of Y` via DomPDF `$pdf->getCanvas()->page_text(...)` in `render()` after `->render()`; right-aligned, plus `Monthly Progress Report No.N`); green table header `#d9ead3`, 1px `#111` borders, 10px body font; `.avoid { page-break-inside: avoid }` on table rows.
- Logos: `data:image/png;base64,` from `Storage::disk('local')->get($party->logo_path)` with type from the extension map (png/jpg/jpeg/webp→ `image/*`; webp is not rendered by DomPDF — skip webp logos); MGE logo from `public/logo.png` like `SiteLogController::logoData()`.
- Report images (1.3, 5.0): embed via the same data-uri helper, `max-width:100%`, `max-height: 110mm`, captions below; progress pairs as a two-column table (Previous | Current) with the label in a left cell.
- Contents page: generated from included sections (titles only; no page numbers in Phase B).
- Controller: `set_time_limit(120)`; filename `"MPR-{project code slug}-No{n}.pdf"`; `->download()`.

- [ ] **Step 1: Failing test** — create a report via the service on seeded data (reuse Task 6 fixtures), `GET /export/pdf` → 200, `Content-Type: application/pdf`, body starts with `%PDF`, and — using a tiny text extraction: `preg_match_all('/\((.*?)\)/', $body)` is unreliable; instead assert the rendered HTML: expose `PdfExporter::html(MonthlyReport)` (the same view rendered to a string) and assert it contains `1.1 PROJECT INFORMATION`, `Two Hundred Eighty-Eight Million Ringgit Only`, the contractor name, and `Page` footer marker; assert a section with `include=false` is absent.
- [ ] **Step 2: FAIL. Step 3: Implement exporter, views, controller route. Step 4: PASS** (also open the PDF locally once: `php artisan tinker` → write `render()->output()` to `storage/app/mpr-preview.pdf` and view it; report any layout problem in the task report). **Step 5: Pint, commit** `"Add portrait PDF export for monthly reports"`.

---

### Task 8: Frontend service, menu, route, list page + New report dialog

**Files:**
- Create: `resources/js/services/monthlyReportService.js`, `resources/js/pages/reports/MonthlyReports.jsx`
- Modify: `resources/js/app.jsx`, `resources/js/layouts/DashboardLayout.jsx`

**Interfaces:**
- Service: `list(params)`, `listForProject(projectId)`, `create(projectId, data)`, `get(id)`, `update(id, data)`, `saveSection(id, key, data)`, `regenerate(id, key?)`, `finalise(id)`, `reopen(id)`, `remove(id)`, `getPdfUrl(id)` → `/api/monthly-reports/${id}/export/pdf`.
- Menu: under Projects, after Contracts: `{ name: 'Monthly Reports', href: '/projects/monthly-reports', icon: HiOutlineDocumentReport, permission: 'reports.view' }` (check the icon exists in `react-icons/hi`; else `HiOutlineDocumentText`).
- Routes: `/projects/monthly-reports` → `MonthlyReports`, `/projects/monthly-reports/:id` → `MonthlyReportEditor` (Task 9; add the route now with the component file created as a stub in this task that renders "Loading…").
- List page: filters project (from `projectService.list({per_page:100})`) and status; table columns Project, Report No, Period, Status, Generated, actions (Open, PDF link `<a href target="_blank" rel="noopener">`, Delete when draft with `useConfirm`). **New report** button opens a dialog: project select, period end date (default: today's period end from cutoff — call `reportDataService.suggestPeriod(projectId, today)` to get `period_start/period_end/period_no`), report no (default from the API's next number: show `listForProject` max+1), month label (auto from period end, editable), "Copy static sections from previous report" checkbox (enabled when a previous report exists; sends `copy_from_report_id`). On create → navigate to the editor.

- [ ] **Step 1: Implement. Step 2: `npm run build`; commit assets.** `"Add Monthly Reports list page and navigation"`.

---

### Task 9: Editor shell + Value/Text/Table/Image section editors + regenerate + finalise + PDF

**Files:**
- Create: `resources/js/pages/reports/MonthlyReportEditor.jsx` (replace stub), `resources/js/pages/reports/editor/sectionConfig.js`, `ValueSection.jsx`, `TableSection.jsx`, `TextSection.jsx`, `ImageSection.jsx`, `SectionNotes.jsx`

**Interfaces:**
- `sectionConfig.js` exports `SECTION_EDITORS = { cover: {type:'value', fields:[{key:'project_title',label:'Project Title'}, {key:'evaluation_date',label:'Evaluation Date',type:'date'}], signatories:true}, '1.1': {type:'table', fixedRows:true, columns:[{key:'label',label:'Item',readOnly:true},{key:'value',label:'Value',multiline:true}]}, '1.2': {type:'table', columns:[{key:'party',label:'Party'},{key:'company',label:'Company'},{key:'address',label:'Address',multiline:true},{key:'contacts',label:'Contacts',type:'contacts'}]}, '1.3': {type:'image', collection:'images'}, '1.4': {type:'text'}, '1.5': {type:'table', columns:[{key:'designation',label:'Designation'},{key:'nos',label:'Nos.',type:'number'}]}, '2.1': {type:'progress'}, '2.2': {type:'text'}, '2.3': {type:'table', columns:[ipc_no, submission_date, evaluation_date, claim_amount(number), certified(number), wjp_current(number), wjp_cumulative(number), paid_current(number), paid_cumulative(number), remarks]}, '2.4': {type:'text'}, '2.5': {type:'table', columns:[no, task, duration, start, finish, actual, plan]}, '2.6': {type:'table', columns:[no, title, issue(multiline), reg_number, submitted, reply, duration, status, impact(multiline)]}, '3.1': {type:'groups-numbers'}, '3.2': {type:'groups-table', columns:[no, reference, title, issued, approved, reminder, status]}, '3.4': {type:'table', columns:[no, ref_no, name, date, result, remarks]}, '3.6': {type:'table', columns:[no, drawing_no, title]}, '3.7': {type:'table', columns:[no, description, date, location]}, '4.1': {type:'matrix', grouped:true}, '4.2': {type:'matrix'}, '4.3': {type:'text'}, '5.0': {type:'image', collections:['site_access','key_plan','pairs']} }`.
- `TableSection` — generic grid from `merged.rows`; add/remove/reorder rows unless `fixedRows`; edits are held locally and **Save** sends `overrides: {_rows: rows}`; **Reset to system data** sends `overrides: {}`; the `contacts` column type renders a nested mini-list (name/designation/tel/email). `groups-table` renders one `TableSection` per group and saves `overrides: {groups: [...]}` (whole groups array). `groups-numbers` (3.1) renders the 3×3 numbers per group editable, saving the whole `groups` array. `matrix` (4.1/4.2) renders the day columns with sticky first column; counts editable; saves whole `groups`/`rows`. `progress` (2.1) renders the two fixed tables with `prev`/`cur` editable; saves `physical`/`financial` objects.
- `ValueSection` — form of fields + (cover) the three signatory cards; saves `overrides: {field: value, signatories: [...]}`.
- `TextSection` — read-only render of the data (tree as nested list; series as a small table; weather as a day list with rain hours) + `SectionNotes`.
- `ImageSection` — thumbnails from `url` with caption inputs; saves `overrides: {images: [...]}` / `{site_access: [...], key_plan: [...], pairs: [...]}`; a link "Manage images in Report Data" → `/projects/{id}?tab=report-data` (ProjectDetail supports `?tab=`? if not, just link to the project).
- Shell: header (project name/code, report title, period, status badge, buttons **Save all** (saves dirty sections sequentially), **Regenerate all** (confirm), **Export PDF** (`<a href target=_blank rel=noopener>`), **Finalise** / **Reopen** (confirm)); left nav lists sections with include toggles and badges (`edited` when `overrides` non-empty, `notes` when notes set); right pane renders the editor by `SECTION_EDITORS[key].type`; each section has its own **Regenerate this section** (confirm when it has overrides — explain overrides are kept). Final reports render everything disabled with a banner.

- [ ] **Step 1: Implement. Step 2: `npm run build`; commit assets.** `"Add monthly report editor"`.

---

### Task 10: Panduan + local demo + full verification

**Files:**
- Modify: `resources/js/pages/panduan/content.js` (Sistem → Projek: group "Laporan Bulanan (Monthly Reports)": buka Projects › Monthly Reports, New report (projek, tempoh, no. laporan, salin dari laporan lepas), edit setiap seksyen, "Regenerate" vs suntingan, Finalise, Export PDF; nota: carta S-curve, landscape, Word dan import program datang dalam fasa seterusnya)
- Local only (git-excluded): extend `database/seeders/ReportDataDemoSeeder.php` to also create a report No.3 for the demo period via `MonthlyReportService::create()` (idempotent: delete existing demo reports for the project first).

- [ ] **Step 1: Panduan group + seeder extension. Step 2: `php vendor/bin/pint --test && php -d memory_limit=2G vendor/bin/phpunit && npm run build`; run the seeder twice; open the editor and export the PDF locally once (build-only if no browser session). Step 3: commit** `"Document monthly reports in the user guide"` (Panduan + assets only).

---

## Self-review

**Spec coverage (Phase B = §3.14, §4 without charts, §5.1 portrait only, §6 report routes, §7 list/new/editor):** tables/models → T1; builders for every key → T2–T5 (2.5 placeholder by design, §11.8); service + API → T6; PDF → T7; list/new → T8; editor → T9; guide → T10. Charts (2.2/2.4 images), landscape merge, Gantt append, DOCX, programme import are explicitly Phases C–E.

**Placeholder scan:** Tasks 3–5 and 8–9 specify builders/editors as exact shapes, queries and behaviours rather than full code; no "TBD"/"similar to" phrases; each test lists concrete fixtures and assertions.

**Type consistency:** `SectionRegistry::TITLES`, `AbstractBuilder` constructor `(string $key)`, `SectionMerger::merge/validateOverrides`, `ReportContext::for/party/periodLabel`, `MonthlyReportService` method names, service function names in T8 and editor types in T9 all match across tasks.
