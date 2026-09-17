<?php

namespace Tests\Feature\MonthlyReport;

use App\Models\MonthlyReport;
use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\ProjectParty;
use App\Models\ProjectProgressPeriod;
use App\Models\User;
use App\Services\MonthlyReport\Export\PdfExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MonthlyReportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['reports.view', 'reports.manage', 'projects.view'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->manager = User::create(['first_name' => 'M', 'last_name' => 'Grr', 'email' => 'mgr-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $this->manager->givePermissionTo(['reports.view', 'reports.manage', 'projects.view']);

        $this->viewer = User::create(['first_name' => 'V', 'last_name' => 'Wr', 'email' => 'vw-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $this->viewer->givePermissionTo(['reports.view', 'projects.view']);
    }

    private function project(): Project
    {
        return Project::create(['name' => 'RTB SG. MUAR', 'code' => 'JPS/IP/BPB/'.uniqid(), 'status' => 'in_progress', 'report_cutoff_day' => 15]);
    }

    private function seedProject(): array
    {
        $project = $this->project();
        ProjectContract::create(['project_id' => $project->id, 'title' => 'Main', 'is_main' => true, 'contract_sum' => 288000000]);
        ProjectParty::create(['project_id' => $project->id, 'name' => 'MULTI GREEN ENGINEERING SDN BHD', 'type' => 'main_contractor', 'report_role' => 'contractor', 'address' => 'No. 35, Segamat', 'sort_order' => 6]);
        $period = ProjectProgressPeriod::create([
            'project_id' => $project->id, 'period_no' => 3, 'period_start' => '2025-12-16', 'period_end' => '2026-01-15',
            'planning_days_completion' => 497, 'physical_scheduled_pct' => 2, 'physical_actual_pct' => 4,
            'financial_scheduled_pct' => 2, 'financial_actual_pct' => 4, 'financial_actual_amount' => 400000,
        ]);

        return [$project, $period];
    }

    public function test_create_generates_all_sections_with_defaults(): void
    {
        [$project, $period] = $this->seedProject();

        $res = $this->actingAs($this->manager)->postJson("/api/projects/{$project->id}/monthly-reports", [
            'period_id' => $period->id,
        ])->assertCreated();

        $this->assertCount(21, $res->json('data.sections'));
        $section25 = collect($res->json('data.sections'))->firstWhere('key', '2.5');
        $this->assertFalse($section25['include']);
        $cover = collect($res->json('data.sections'))->firstWhere('key', 'cover');
        $this->assertSame('01 (SATU)', $cover['merged']['report_no_words']);
        $this->assertSame(1, $res->json('data.report_no'));
        $this->assertSame('Monthly Progress Report No.1', $res->json('data.title'));
        $this->assertSame('January 2026', $res->json('data.month_label'));
    }

    public function test_save_section_override_merges_without_touching_stored_data(): void
    {
        [$project, $period] = $this->seedProject();
        $reportId = $this->actingAs($this->manager)->postJson("/api/projects/{$project->id}/monthly-reports", ['period_id' => $period->id])->json('data.id');

        $original = MonthlyReport::find($reportId)->sections()->where('key', '1.1')->first();
        $originalRows = $original->data['rows'];

        $newRows = $originalRows;
        $newRows[0]['value'] = 'Overridden Title';

        $res = $this->actingAs($this->manager)->putJson("/api/monthly-reports/{$reportId}/sections/1.1", [
            'overrides' => ['_rows' => $newRows],
        ])->assertOk();

        $this->assertSame('Overridden Title', $res->json('data.merged.rows.0.value'));
        $fresh = MonthlyReport::find($reportId)->sections()->where('key', '1.1')->first();
        $this->assertSame($originalRows, $fresh->data['rows']);

        $this->actingAs($this->manager)->putJson("/api/monthly-reports/{$reportId}/sections/1.1", [
            'overrides' => ['foo' => 'bar'],
        ])->assertStatus(422);
    }

    public function test_regenerate_rebuilds_data_and_keeps_overrides(): void
    {
        [$project, $period] = $this->seedProject();
        $reportId = $this->actingAs($this->manager)->postJson("/api/projects/{$project->id}/monthly-reports", ['period_id' => $period->id])->json('data.id');

        $this->actingAs($this->manager)->putJson("/api/monthly-reports/{$reportId}/sections/1.1", [
            'overrides' => ['_rows' => [['label' => 'Custom', 'value' => 'X']]],
        ])->assertOk();

        $res = $this->actingAs($this->manager)->postJson("/api/monthly-reports/{$reportId}/regenerate?key=1.1")->assertOk();

        $section = collect($res->json('data.sections'))->firstWhere('key', '1.1');
        $this->assertNotEmpty($section['data']['rows']);
        $this->assertSame([['label' => 'Custom', 'value' => 'X']], $section['overrides']['_rows']);
        $this->assertSame('Custom', $section['merged']['rows'][0]['label']);
    }

    public function test_finalise_locks_the_report_and_reopen_unlocks_it(): void
    {
        [$project, $period] = $this->seedProject();
        $reportId = $this->actingAs($this->manager)->postJson("/api/projects/{$project->id}/monthly-reports", ['period_id' => $period->id])->json('data.id');

        $this->actingAs($this->manager)->postJson("/api/monthly-reports/{$reportId}/finalise")->assertOk();
        $this->assertSame('final', MonthlyReport::find($reportId)->status);

        $this->actingAs($this->manager)->putJson("/api/monthly-reports/{$reportId}/sections/1.1", ['notes' => 'x'])->assertStatus(422);
        $this->actingAs($this->manager)->deleteJson("/api/monthly-reports/{$reportId}")->assertStatus(422);

        $this->actingAs($this->manager)->postJson("/api/monthly-reports/{$reportId}/reopen")->assertOk();
        $this->assertSame('draft', MonthlyReport::find($reportId)->status);

        $this->actingAs($this->manager)->deleteJson("/api/monthly-reports/{$reportId}")->assertOk();
        $this->assertNull(MonthlyReport::find($reportId));
    }

    public function test_view_only_user_cannot_create_report(): void
    {
        [$project, $period] = $this->seedProject();

        $this->actingAs($this->viewer)->postJson("/api/projects/{$project->id}/monthly-reports", [
            'period_id' => $period->id,
        ])->assertStatus(403);
    }

    public function test_copy_from_report_id_must_belong_to_the_same_project(): void
    {
        [$project, $period] = $this->seedProject();
        [$otherProject, $otherPeriod] = $this->seedProject();

        $otherReportId = $this->actingAs($this->manager)->postJson("/api/projects/{$otherProject->id}/monthly-reports", ['period_id' => $otherPeriod->id])->json('data.id');

        $this->actingAs($this->manager)->postJson("/api/projects/{$project->id}/monthly-reports", [
            'period_id' => $period->id,
            'copy_from_report_id' => $otherReportId,
        ])->assertStatus(422);
    }

    public function test_duplicate_report_no_within_a_project_is_rejected(): void
    {
        [$project, $period] = $this->seedProject();

        $this->actingAs($this->manager)->postJson("/api/projects/{$project->id}/monthly-reports", [
            'period_id' => $period->id,
            'report_no' => 5,
        ])->assertCreated();

        $period2 = $period->replicate();
        $period2->period_no = $period->period_no + 1;
        $period2->period_start = '2026-01-16';
        $period2->period_end = '2026-02-15';
        $period2->save();

        $this->actingAs($this->manager)->postJson("/api/projects/{$project->id}/monthly-reports", [
            'period_id' => $period2->id,
            'report_no' => 5,
        ])->assertStatus(422);
    }

    public function test_list_is_filterable_by_project(): void
    {
        [$project, $period] = $this->seedProject();
        [$project2, $period2] = $this->seedProject();

        $this->actingAs($this->manager)->postJson("/api/projects/{$project->id}/monthly-reports", ['period_id' => $period->id])->assertCreated();
        $this->actingAs($this->manager)->postJson("/api/projects/{$project2->id}/monthly-reports", ['period_id' => $period2->id])->assertCreated();

        $res = $this->actingAs($this->manager)->getJson('/api/monthly-reports?project_id='.$project->id)->assertOk();
        $this->assertCount(1, $res->json('data.data'));
        $this->assertSame($project->id, $res->json('data.data.0.project.id'));
    }

    public function test_update_save_section_and_regenerate_are_blocked_on_a_finalised_report(): void
    {
        [$project, $period] = $this->seedProject();
        $reportId = $this->actingAs($this->manager)->postJson("/api/projects/{$project->id}/monthly-reports", ['period_id' => $period->id])->json('data.id');

        $this->actingAs($this->manager)->postJson("/api/monthly-reports/{$reportId}/finalise")->assertOk();

        $this->actingAs($this->manager)->putJson("/api/monthly-reports/{$reportId}", ['title' => 'New Title'])->assertStatus(422);
        $this->actingAs($this->manager)->putJson("/api/monthly-reports/{$reportId}/sections/1.1", ['notes' => 'x'])->assertStatus(422);
        $this->actingAs($this->manager)->postJson("/api/monthly-reports/{$reportId}/regenerate?key=1.1")->assertStatus(422);
    }

    public function test_ill_typed_override_is_rejected(): void
    {
        [$project, $period] = $this->seedProject();
        $reportId = $this->actingAs($this->manager)->postJson("/api/projects/{$project->id}/monthly-reports", ['period_id' => $period->id])->json('data.id');

        $this->actingAs($this->manager)->putJson("/api/monthly-reports/{$reportId}/sections/1.1", [
            'overrides' => ['rows' => 'x'],
        ])->assertStatus(422);
    }

    public function test_view_only_user_gets_403_on_manage_actions(): void
    {
        [$project, $period] = $this->seedProject();
        $reportId = $this->actingAs($this->manager)->postJson("/api/projects/{$project->id}/monthly-reports", ['period_id' => $period->id])->json('data.id');

        $this->actingAs($this->viewer)->putJson("/api/monthly-reports/{$reportId}/sections/1.1", ['notes' => 'x'])->assertStatus(403);
        $this->actingAs($this->viewer)->postJson("/api/monthly-reports/{$reportId}/regenerate?key=1.1")->assertStatus(403);
        $this->actingAs($this->viewer)->postJson("/api/monthly-reports/{$reportId}/finalise")->assertStatus(403);
        $this->actingAs($this->viewer)->deleteJson("/api/monthly-reports/{$reportId}")->assertStatus(403);
    }

    public function test_destroy_finalised_report_returns_422(): void
    {
        [$project, $period] = $this->seedProject();
        $reportId = $this->actingAs($this->manager)->postJson("/api/projects/{$project->id}/monthly-reports", ['period_id' => $period->id])->json('data.id');

        $this->actingAs($this->manager)->postJson("/api/monthly-reports/{$reportId}/finalise")->assertOk();

        $this->actingAs($this->manager)->deleteJson("/api/monthly-reports/{$reportId}")->assertStatus(422);
        $this->assertNotNull(MonthlyReport::find($reportId));
    }

    public function test_cover_pdf_html_uses_report_level_signatory(): void
    {
        [$project, $period] = $this->seedProject();
        $reportId = $this->actingAs($this->manager)->postJson("/api/projects/{$project->id}/monthly-reports", ['period_id' => $period->id])->json('data.id');

        $this->actingAs($this->manager)->putJson("/api/monthly-reports/{$reportId}", [
            'signatories' => [
                ['slot' => 'prepared', 'name' => 'Edited Signatory Name', 'designation' => 'Site Agent', 'company' => 'MGE'],
                ['slot' => 'verified', 'name' => '', 'designation' => '', 'company' => ''],
                ['slot' => 'accepted', 'name' => '', 'designation' => '', 'company' => ''],
            ],
        ])->assertOk();

        $report = MonthlyReport::with(['sections', 'project', 'period'])->findOrFail($reportId);
        $html = app(PdfExporter::class)->html($report);

        $this->assertStringContainsString('Edited Signatory Name', $html);
    }

    public function test_creating_two_reports_for_the_same_period_end_reuses_one_period(): void
    {
        $project = $this->project();
        ProjectContract::create(['project_id' => $project->id, 'title' => 'Main', 'is_main' => true, 'contract_sum' => 288000000]);
        ProjectParty::create(['project_id' => $project->id, 'name' => 'MULTI GREEN ENGINEERING SDN BHD', 'type' => 'main_contractor', 'report_role' => 'contractor', 'address' => 'No. 35, Segamat', 'sort_order' => 6]);
        \App\Models\ProjectScheduleBaseline::create(['project_id' => $project->id, 'month' => '2026-01-01', 'scheduled_physical_pct' => 2, 'scheduled_financial_amount' => 200000, 'scheduled_financial_pct' => 2]);

        $beforeCount = ProjectProgressPeriod::count();

        $first = $this->actingAs($this->manager)->postJson("/api/projects/{$project->id}/monthly-reports", [
            'period_end' => '2026-01-15',
            'report_no' => 1,
        ])->assertCreated();

        $second = $this->actingAs($this->manager)->postJson("/api/projects/{$project->id}/monthly-reports", [
            'period_end' => '2026-01-15',
            'report_no' => 2,
        ])->assertCreated();

        $afterCount = ProjectProgressPeriod::count();
        $this->assertSame($beforeCount + 1, $afterCount);
        $this->assertSame($first->json('data.period.id'), $second->json('data.period.id'));
    }
}
