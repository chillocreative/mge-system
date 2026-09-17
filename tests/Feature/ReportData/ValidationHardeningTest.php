<?php

namespace Tests\Feature\ReportData;

use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Final-review fix wave — validation/null-handling hardening across the
 * Report Data endpoints (see .superpowers/sdd/.../final-fix-report.md).
 */
class ValidationHardeningTest extends TestCase
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

    public function test_null_percentage_field_on_create_does_not_crash_and_stores_zero(): void
    {
        $project = $this->project();

        $res = $this->actingAs($this->editor)->postJson("/api/projects/{$project->id}/progress-periods", [
            'period_no' => 1,
            'period_start' => '2025-12-16',
            'period_end' => '2026-01-15',
            'physical_scheduled_pct' => null,
            'physical_actual_pct' => 5,
            'financial_scheduled_pct' => 10,
            'financial_actual_pct' => 12,
        ])->assertCreated();

        $this->assertEquals(0, $res->json('data.physical_scheduled_pct'));
    }

    public function test_duplicate_baseline_months_are_rejected(): void
    {
        $project = $this->project();

        $this->actingAs($this->editor)->putJson("/api/projects/{$project->id}/schedule-baseline", ['rows' => [
            ['month' => '2026-01', 'scheduled_physical_pct' => 2],
            ['month' => '2026-01', 'scheduled_physical_pct' => 5],
        ]])->assertStatus(422);
    }

    public function test_resource_category_names_are_unique_case_insensitively(): void
    {
        $project = $this->project();

        $this->actingAs($this->editor)->putJson("/api/projects/{$project->id}/resource-categories?kind=worker", ['rows' => [
            ['name' => 'Mason', 'sort_order' => 0],
            ['name' => 'mason', 'sort_order' => 1],
        ]])->assertStatus(422);
    }

    public function test_creating_a_second_main_contract_leaves_exactly_one_main(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);

        $this->actingAs($this->editor)->postJson('/api/project-contracts', [
            'project_id' => $project->id,
            'title' => 'Contract A',
            'is_main' => true,
        ])->assertCreated();

        $this->actingAs($this->editor)->postJson('/api/project-contracts', [
            'project_id' => $project->id,
            'title' => 'Contract B',
            'is_main' => true,
        ])->assertCreated();

        $this->assertSame(1, ProjectContract::where('project_id', $project->id)->where('is_main', true)->count());
        $this->assertSame('Contract B', ProjectContract::where('project_id', $project->id)->where('is_main', true)->first()->title);
    }
}
