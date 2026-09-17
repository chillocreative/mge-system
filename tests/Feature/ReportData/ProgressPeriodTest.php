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

    public function test_update_recalculates_derived_fields_unless_overridden(): void
    {
        $project = $this->project();
        $created = $this->actingAs($this->editor)->postJson("/api/projects/{$project->id}/progress-periods", [
            'period_no' => 3, 'period_start' => '2025-12-16', 'period_end' => '2026-01-15',
            'planning_days_completion' => 497,
            'physical_scheduled_pct' => 2, 'physical_actual_pct' => 4,
            'financial_scheduled_pct' => 15, 'financial_actual_pct' => 13,
        ])->assertCreated();
        $this->assertSame('AHEAD', $created->json('data.physical_status'));
        $this->assertSame(10, $created->json('data.ahead_delay_days'));
        $periodId = $created->json('data.id');

        // (a) Updating only physical_actual_pct must recalculate ahead_delay_days/physical_status,
        // not carry over the stale AHEAD/10 values from creation.
        $res = $this->actingAs($this->editor)->putJson("/api/projects/{$project->id}/progress-periods/{$periodId}", [
            'physical_actual_pct' => 1,
        ])->assertOk();
        $this->assertSame('DELAY', $res->json('data.physical_status'));
        $this->assertSame(-5, $res->json('data.ahead_delay_days')); // round(-1/100 * 497)

        // (b) Explicit overrides in the same update are kept, not recomputed.
        $res = $this->actingAs($this->editor)->putJson("/api/projects/{$project->id}/progress-periods/{$periodId}", [
            'physical_actual_pct' => 1,
            'physical_status' => 'ON TRACK',
            'ahead_delay_days' => 0,
        ])->assertOk();
        $this->assertSame('ON TRACK', $res->json('data.physical_status'));
        $this->assertSame(0, $res->json('data.ahead_delay_days'));

        // (c) Updating period_end alone must not trip an "after:period_start" failure
        // when period_start isn't part of this request.
        $this->actingAs($this->editor)->putJson("/api/projects/{$project->id}/progress-periods/{$periodId}", [
            'period_end' => '2026-02-15',
        ])->assertOk();
    }
}
