<?php

namespace Tests\Feature\MonthlyReport;

use App\Models\MonthlyReport;
use App\Models\Project;
use App\Models\ProjectProgrammeVersion;
use App\Models\ProjectProgressPeriod;
use App\Services\MonthlyReport\ReportContext;
use App\Services\MonthlyReport\SectionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkProgrammeSectionTest extends TestCase
{
    use RefreshDatabase;

    private function context(int $projectId, int $periodId): ReportContext
    {
        $report = MonthlyReport::create([
            'project_id' => $projectId, 'period_id' => $periodId, 'report_no' => 3,
            'title' => 'Monthly Progress Report No.3', 'month_label' => 'January 2026',
        ]);

        return ReportContext::for($report);
    }

    private function project(): Project
    {
        return Project::create(['name' => 'RTB SG. MUAR', 'code' => 'JPS/IP/BPB/10/2025', 'status' => 'in_progress']);
    }

    private function period(Project $project): ProjectProgressPeriod
    {
        return ProjectProgressPeriod::create([
            'project_id' => $project->id, 'period_no' => 3, 'period_start' => '2025-12-16', 'period_end' => '2026-01-15',
            'physical_scheduled_pct' => 2, 'physical_actual_pct' => 4, 'financial_scheduled_pct' => 2, 'financial_actual_pct' => 4,
            'financial_actual_amount' => 400000,
        ]);
    }

    public function test_placeholder_note_when_no_programme_version_exists(): void
    {
        $project = $this->project();
        $period = $this->period($project);

        $data = SectionRegistry::make('2.5')->build($this->context($project->id, $period->id));

        $this->assertSame(1, $data['schema']);
        $this->assertNull($data['version']);
        $this->assertSame('No work programme has been imported for this project yet (Report Data › Work Programme).', $data['note']);
        $this->assertSame([], $data['rows']);
    }

    public function test_picks_the_current_version_over_a_later_non_current_one(): void
    {
        $project = $this->project();
        $period = $this->period($project);

        $older = ProjectProgrammeVersion::create([
            'project_id' => $project->id, 'label' => 'Baseline', 'status_date' => '2026-01-01',
            'source_type' => 'manual', 'is_current' => true, 'activity_count' => 1,
        ]);
        $older->activities()->create([
            'seq' => 1, 'outline_level' => 1, 'name' => 'Older current task', 'duration_days' => 5,
            'start' => '2026-01-01', 'finish' => '2026-01-05', 'actual_pct' => 50, 'plan_pct' => 60, 'is_summary' => false,
        ]);

        ProjectProgrammeVersion::create([
            'project_id' => $project->id, 'label' => 'Revision 1', 'status_date' => '2026-02-01',
            'source_type' => 'manual', 'is_current' => false, 'activity_count' => 0,
        ]);

        $data = SectionRegistry::make('2.5')->build($this->context($project->id, $period->id));

        $this->assertSame('Baseline', $data['version']['label']);
        $this->assertCount(1, $data['rows']);
        $this->assertSame('Older current task', $data['rows'][0]['task']);
    }

    public function test_falls_back_to_latest_by_status_date_when_none_is_current(): void
    {
        $project = $this->project();
        $period = $this->period($project);

        ProjectProgrammeVersion::create([
            'project_id' => $project->id, 'label' => 'Baseline', 'status_date' => '2026-01-01',
            'source_type' => 'manual', 'is_current' => false, 'activity_count' => 0,
        ]);
        $latest = ProjectProgrammeVersion::create([
            'project_id' => $project->id, 'label' => 'Revision 1', 'status_date' => '2026-02-01',
            'source_type' => 'manual', 'is_current' => false, 'activity_count' => 0,
        ]);

        $data = SectionRegistry::make('2.5')->build($this->context($project->id, $period->id));

        $this->assertSame($latest->id, $data['version']['id']);
        $this->assertSame('Revision 1', $data['version']['label']);
    }

    public function test_rows_are_formatted_with_summary_and_level_passthrough(): void
    {
        $project = $this->project();
        $period = $this->period($project);

        $version = ProjectProgrammeVersion::create([
            'project_id' => $project->id, 'label' => 'Baseline', 'status_date' => '2026-01-15',
            'source_type' => 'manual', 'is_current' => true, 'activity_count' => 3,
        ]);

        $version->activities()->create([
            'seq' => 1, 'outline_level' => 1, 'name' => 'Earthworks', 'duration_days' => null,
            'start' => null, 'finish' => null, 'actual_pct' => null, 'plan_pct' => null, 'is_summary' => true,
        ]);
        $version->activities()->create([
            'seq' => 2, 'outline_level' => 2, 'name' => 'Excavation', 'duration_days' => 1,
            'start' => '2026-01-02', 'finish' => '2026-01-02', 'actual_pct' => 45.5, 'plan_pct' => 50, 'is_summary' => false,
        ]);
        $version->activities()->create([
            'seq' => 3, 'outline_level' => 2, 'name' => 'Backfilling', 'duration_days' => 12,
            'start' => '2026-01-03', 'finish' => '2026-01-15', 'actual_pct' => 0, 'plan_pct' => 100, 'is_summary' => false,
        ]);

        $data = SectionRegistry::make('2.5')->build($this->context($project->id, $period->id));

        $this->assertSame(['id' => $version->id, 'label' => 'Baseline', 'status_date' => '15/01/2026'], $data['version']);
        $this->assertNull($data['note']);

        [$summary, $excavation, $backfilling] = $data['rows'];

        $this->assertSame(1, $summary['level']);
        $this->assertTrue($summary['summary']);
        $this->assertSame('', $summary['duration']);
        $this->assertSame('', $summary['start']);
        $this->assertSame('', $summary['finish']);
        $this->assertSame('', $summary['actual']);
        $this->assertSame('', $summary['plan']);

        $this->assertSame(2, $excavation['level']);
        $this->assertFalse($excavation['summary']);
        $this->assertSame('1 day', $excavation['duration']);
        $this->assertSame('02/01/2026', $excavation['start']);
        $this->assertSame('45.5%', $excavation['actual']);
        $this->assertSame('50%', $excavation['plan']);

        $this->assertSame('12 days', $backfilling['duration']);
        $this->assertSame('0%', $backfilling['actual']);
        $this->assertSame('100%', $backfilling['plan']);
    }
}
