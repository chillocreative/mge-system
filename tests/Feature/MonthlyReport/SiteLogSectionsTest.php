<?php

namespace Tests\Feature\MonthlyReport;

use App\Models\MonthlyReport;
use App\Models\Project;
use App\Models\ProjectProgressPeriod;
use App\Models\ProjectResourceCategory;
use App\Models\ReportImage;
use App\Models\SiteLog;
use App\Models\User;
use App\Services\MonthlyReport\ReportContext;
use App\Services\MonthlyReport\SectionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteLogSectionsTest extends TestCase
{
    use RefreshDatabase;

    private function context(): ReportContext
    {
        $project = Project::create(['name' => 'Site Log Test', 'code' => 'SLT-'.uniqid(), 'status' => 'in_progress']);
        $user = User::create(['first_name' => 'Log', 'last_name' => 'Ger', 'email' => 'logger-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);

        ProjectResourceCategory::create(['project_id' => $project->id, 'kind' => 'worker', 'group' => 'Tradesman', 'name' => 'General Worker', 'sort_order' => 0, 'active' => true]);
        ProjectResourceCategory::create(['project_id' => $project->id, 'kind' => 'worker', 'group' => 'Management Team', 'name' => 'Project Manager', 'sort_order' => 1, 'active' => true]);

        $previousPeriod = ProjectProgressPeriod::create(['project_id' => $project->id, 'period_no' => 1, 'period_start' => '2025-11-16', 'period_end' => '2025-12-15']);
        $period = ProjectProgressPeriod::create(['project_id' => $project->id, 'period_no' => 2, 'period_start' => '2025-12-16', 'period_end' => '2025-12-18']);

        $log1 = SiteLog::create(['project_id' => $project->id, 'log_date' => '2025-12-16', 'title' => 'Day 1', 'logged_by' => $user->id]);
        $log1->workers()->createMany([
            ['worker_type' => 'General Worker', 'count' => 30],
            ['worker_type' => 'Project Manager', 'count' => 1],
            ['worker_type' => 'Casual Worker', 'count' => 2],
        ]);
        $log1->machinery()->create(['machinery_type' => 'Excavator', 'quantity' => 5]);
        $log1->weatherEvents()->createMany([
            ['condition' => 'rain_start', 'event_time' => '09:00'],
            ['condition' => 'rain_stop', 'event_time' => '10:30'],
        ]);

        // 2025-12-17 has no site log at all.

        $log3 = SiteLog::create(['project_id' => $project->id, 'log_date' => '2025-12-18', 'title' => 'Day 3', 'logged_by' => $user->id]);
        $log3->workers()->create(['worker_type' => 'General Worker', 'count' => 30]);

        ReportImage::create(['project_id' => $project->id, 'section' => 'location', 'file_path' => 'a', 'file_name' => 'a.jpg', 'caption' => 'Front gate', 'sort_order' => 0]);
        ReportImage::create(['project_id' => $project->id, 'section' => 'site_access', 'file_path' => 'b', 'file_name' => 'b.jpg', 'sort_order' => 0]);
        ReportImage::create(['project_id' => $project->id, 'section' => 'progress_key_plan', 'file_path' => 'c', 'file_name' => 'c.jpg', 'sort_order' => 0]);
        ReportImage::create(['project_id' => $project->id, 'section' => 'progress', 'label' => 'Aerial 1', 'period_id' => $previousPeriod->id, 'file_path' => 'd', 'file_name' => 'd.jpg', 'sort_order' => 0]);
        ReportImage::create(['project_id' => $project->id, 'section' => 'progress', 'label' => 'Aerial 1', 'period_id' => $period->id, 'file_path' => 'e', 'file_name' => 'e.jpg', 'sort_order' => 0]);

        $report = MonthlyReport::create(['project_id' => $project->id, 'period_id' => $period->id, 'report_no' => 1, 'title' => 'Report', 'month_label' => 'December 2025']);

        return ReportContext::for($report);
    }

    public function test_trade_worker_matrix_has_groups_and_day_counts(): void
    {
        $data = SectionRegistry::make('4.1')->build($this->context());

        $this->assertSame(1, $data['schema']);
        $this->assertCount(3, $data['days']);
        $this->assertSame(['2025-12-16', '2025-12-17', '2025-12-18'], array_column($data['days'], 'date'));

        $groupLabels = array_column($data['groups'], 'label');
        $this->assertContains('Management Team', $groupLabels);
        $this->assertContains('Tradesman', $groupLabels);

        $tradesman = collect($data['groups'])->firstWhere('label', 'Tradesman');
        $generalWorker = collect($tradesman['rows'])->firstWhere('description', 'General Worker');
        $this->assertSame([30, null, 30], $generalWorker['counts']);

        // Worker type logged but not in the category list is appended under Tradesman.
        $casual = collect($tradesman['rows'])->firstWhere('description', 'Casual Worker');
        $this->assertNotNull($casual);
        $this->assertSame([2, null, 0], $casual['counts']);

        $management = collect($data['groups'])->firstWhere('label', 'Management Team');
        $pm = collect($management['rows'])->firstWhere('description', 'Project Manager');
        $this->assertSame([1, null, 0], $pm['counts']);

        $this->assertSame([33, null, 30], $data['totals']);
    }

    public function test_machinery_matrix_has_no_groups(): void
    {
        $data = SectionRegistry::make('4.2')->build($this->context());

        $this->assertArrayNotHasKey('groups', $data);
        $excavator = collect($data['rows'])->firstWhere('description', 'Excavator');
        $this->assertSame([5, null, 0], $excavator['counts']);
        $this->assertSame([5, null, 0], $data['totals']);
    }

    public function test_weather_report_summarises_rain_intervals(): void
    {
        $data = SectionRegistry::make('4.3')->build($this->context());

        $this->assertCount(3, $data['days']);
        $this->assertSame([[540, 630]], $data['days'][0]['intervals']);
        $this->assertSame([], $data['days'][1]['intervals']);
        $this->assertSame(3, $data['summary']['total_days']);
        $this->assertSame(1, $data['summary']['raining_days']);
        $this->assertSame(1.5, $data['summary']['raining_hours']);
    }

    public function test_project_location_lists_images(): void
    {
        $data = SectionRegistry::make('1.3')->build($this->context());

        $this->assertCount(1, $data['images']);
        $this->assertSame('Front gate', $data['images'][0]['caption']);
        $this->assertStringContainsString('/report-images/', $data['images'][0]['url']);
    }

    public function test_progress_photograph_pairs_previous_and_current_by_label(): void
    {
        $data = SectionRegistry::make('5.0')->build($this->context());

        $this->assertCount(1, $data['site_access']);
        $this->assertCount(1, $data['key_plan']);
        $this->assertCount(1, $data['pairs']);
        $this->assertSame('Aerial 1', $data['pairs'][0]['label']);
        $this->assertNotNull($data['pairs'][0]['previous']);
        $this->assertNotNull($data['pairs'][0]['current']);
    }

    /** Multi-site projects can log more than one SiteLog on the same date. */
    private function multiLogContext(): ReportContext
    {
        $project = Project::create(['name' => 'Multi Site Log Test', 'code' => 'MSL-'.uniqid(), 'status' => 'in_progress']);
        $user = User::create(['first_name' => 'Log', 'last_name' => 'Ger', 'email' => 'logger-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);

        $period = ProjectProgressPeriod::create(['project_id' => $project->id, 'period_no' => 1, 'period_start' => '2025-12-16', 'period_end' => '2025-12-16']);

        $logA = SiteLog::create(['project_id' => $project->id, 'log_date' => '2025-12-16', 'title' => 'Site A', 'logged_by' => $user->id]);
        $logA->workers()->create(['worker_type' => 'General Worker', 'count' => 30]);
        $logA->machinery()->create(['machinery_type' => 'Excavator', 'quantity' => 2]);
        $logA->weatherEvents()->create(['condition' => 'rain_start', 'event_time' => '09:00']);

        $logB = SiteLog::create(['project_id' => $project->id, 'log_date' => '2025-12-16', 'title' => 'Site B', 'logged_by' => $user->id]);
        $logB->workers()->create(['worker_type' => 'General Worker', 'count' => 10]);
        $logB->machinery()->create(['machinery_type' => 'Excavator', 'quantity' => 3]);
        $logB->weatherEvents()->create(['condition' => 'rain_stop', 'event_time' => '10:00']);

        $report = MonthlyReport::create(['project_id' => $project->id, 'period_id' => $period->id, 'report_no' => 1, 'title' => 'Report', 'month_label' => 'December 2025']);

        return ReportContext::for($report);
    }

    public function test_trade_worker_matrix_sums_counts_across_multiple_logs_on_the_same_day(): void
    {
        $data = SectionRegistry::make('4.1')->build($this->multiLogContext());

        $row = collect($data['groups'][0]['rows'])->firstWhere('description', 'General Worker');
        $this->assertSame([40], $row['counts']);
        $this->assertSame([40], $data['totals']);
    }

    public function test_machinery_matrix_sums_quantities_across_multiple_logs_on_the_same_day(): void
    {
        $data = SectionRegistry::make('4.2')->build($this->multiLogContext());

        $row = collect($data['rows'])->firstWhere('description', 'Excavator');
        $this->assertSame([5], $row['counts']);
        $this->assertSame([5], $data['totals']);
    }

    public function test_weather_report_merges_events_across_multiple_logs_on_the_same_day(): void
    {
        $data = SectionRegistry::make('4.3')->build($this->multiLogContext());

        $this->assertSame([[540, 600]], $data['days'][0]['intervals']);
        $this->assertSame(1, $data['summary']['raining_days']);
        $this->assertSame(1.0, $data['summary']['raining_hours']);
    }
}
