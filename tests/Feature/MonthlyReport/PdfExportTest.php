<?php

namespace Tests\Feature\MonthlyReport;

use App\Models\MonthlyReport;
use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\ProjectParty;
use App\Models\ProjectProgressPeriod;
use App\Models\ProjectScheduleBaseline;
use App\Models\SiteLog;
use App\Models\User;
use App\Services\MonthlyReport\Export\PdfExporter;
use App\Services\MonthlyReport\MonthlyReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PdfExportTest extends TestCase
{
    use RefreshDatabase;

    private function makeReport(): MonthlyReport
    {
        $project = Project::create(['name' => 'RTB SG. MUAR', 'code' => 'JPS/IP/BPB/10/2025', 'status' => 'in_progress', 'report_cutoff_day' => 15]);
        ProjectContract::create(['project_id' => $project->id, 'title' => 'Main', 'contract_no' => 'JPS/IP/BPB/10/2025', 'is_main' => true,
            'contract_sum' => 288000000, 'performance_bond_amount' => 14400000, 'duration_months' => 24, 'dlp_months' => 12, 'lad_per_day' => 52128,
            'possession_date' => '2025-10-31', 'completion_date' => '2027-10-31', 'dlp_start_date' => '2027-11-01', 'dlp_end_date' => '2028-11-01', 'cidb_registration' => 'TBA',
            'insurances' => [['type' => "Contractor's All Risk", 'insurer' => 'PACIFIC INSURANCE', 'policy_no' => 'CEC-E0039188-H1', 'period_from' => '2025-10-31', 'period_to' => '2027-10-31', 'maintenance_from' => '2027-11-01', 'maintenance_to' => '2029-02-12']]]);
        $owner = ProjectParty::create(['project_id' => $project->id, 'name' => 'BAHAGIAN PENGURUSAN BANJIR, JPS MALAYSIA', 'type' => 'client', 'report_role' => 'owner', 'address' => 'Aras 3, Blok A, Cyber 8', 'sort_order' => 0]);
        $owner->contacts()->create(['name' => 'Ir. Marenawati binti Abd Malek', 'email' => 'marenawati@water.gov.my']);
        ProjectParty::create(['project_id' => $project->id, 'name' => 'MULTI GREEN ENGINEERING SDN BHD', 'type' => 'main_contractor', 'report_role' => 'contractor', 'address' => 'No. 35, Segamat', 'sort_order' => 6]);
        $period = ProjectProgressPeriod::create([
            'project_id' => $project->id, 'period_no' => 3, 'period_start' => '2025-12-16', 'period_end' => '2026-01-15',
            'planning_days_completion' => 497, 'physical_scheduled_pct' => 2, 'physical_actual_pct' => 4,
            'financial_scheduled_pct' => 2, 'financial_actual_pct' => 4, 'financial_actual_amount' => 400000,
        ]);

        $user = User::create(['first_name' => 'M', 'last_name' => 'Grr', 'email' => 'mgr-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);

        /** @var MonthlyReportService $service */
        $service = app(MonthlyReportService::class);

        return $service->create($project->id, ['period_id' => $period->id], $user->id);
    }

    /** Project with 3 progress periods + baseline (so 2.2/2.4 have months) and 2 site logs with a rain interval on one day (so 4.3 has data). */
    private function makeReportWithSeries(): MonthlyReport
    {
        $project = Project::create(['name' => 'Series Test', 'code' => 'SER-'.uniqid(), 'status' => 'in_progress']);

        foreach ([['2025-11-01', 1], ['2025-12-01', 1], ['2026-01-01', 2]] as [$month, $pct]) {
            ProjectScheduleBaseline::create(['project_id' => $project->id, 'month' => $month, 'scheduled_physical_pct' => $pct, 'scheduled_financial_amount' => $pct * 100000, 'scheduled_financial_pct' => $pct]);
        }

        ProjectProgressPeriod::create(['project_id' => $project->id, 'period_no' => 1, 'period_start' => '2025-10-16', 'period_end' => '2025-11-15', 'physical_scheduled_pct' => 1, 'physical_actual_pct' => 1, 'financial_scheduled_pct' => 1, 'financial_actual_pct' => 1, 'financial_actual_amount' => 100000]);
        ProjectProgressPeriod::create(['project_id' => $project->id, 'period_no' => 2, 'period_start' => '2025-11-16', 'period_end' => '2025-12-15', 'physical_scheduled_pct' => 1, 'physical_actual_pct' => 1, 'financial_scheduled_pct' => 1, 'financial_actual_pct' => 1, 'financial_actual_amount' => 100000]);
        $period3 = ProjectProgressPeriod::create(['project_id' => $project->id, 'period_no' => 3, 'period_start' => '2025-12-16', 'period_end' => '2026-01-15', 'physical_scheduled_pct' => 2, 'physical_actual_pct' => 4, 'financial_scheduled_pct' => 2, 'financial_actual_pct' => 4, 'financial_actual_amount' => 400000]);

        $user = User::create(['first_name' => 'Log', 'last_name' => 'Ger', 'email' => 'logger-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);

        // 2 site logs (same day), rain_start 10:00 / rain_stop 12:30, split across both logs.
        $logA = SiteLog::create(['project_id' => $project->id, 'log_date' => '2025-12-20', 'title' => 'Site A', 'logged_by' => $user->id]);
        $logA->weatherEvents()->create(['condition' => 'rain_start', 'event_time' => '10:00']);
        $logB = SiteLog::create(['project_id' => $project->id, 'log_date' => '2025-12-20', 'title' => 'Site B', 'logged_by' => $user->id]);
        $logB->weatherEvents()->create(['condition' => 'rain_stop', 'event_time' => '12:30']);

        /** @var MonthlyReportService $service */
        $service = app(MonthlyReportService::class);

        return $service->create($project->id, ['period_id' => $period3->id], $user->id);
    }

    public function test_html_embeds_s_curve_charts_and_weather_grid(): void
    {
        $report = $this->makeReportWithSeries();

        $html = app(PdfExporter::class)->html($report);

        $this->assertSame(2, substr_count($html, 'data:image/svg+xml;base64,'));
        $this->assertStringContainsString('class="weather-grid"', $html);
        $this->assertStringContainsString('Raining hours', $html);
        $this->assertStringNotContainsString('Chart available in a later phase.', $html);
    }

    public function test_html_renders_included_sections_and_excludes_excluded_ones(): void
    {
        $report = $this->makeReport();

        // 2.5 defaults to include = false.
        $this->assertFalse($report->sections()->where('key', '2.5')->first()->include);

        $html = app(PdfExporter::class)->html($report->fresh(['sections', 'project', 'period']));

        $this->assertStringContainsString('1.1 PROJECT INFORMATION', $html);
        $this->assertStringContainsString('Two Hundred Eighty-Eight Million Ringgit Only', $html);
        $this->assertStringContainsString('MULTI GREEN ENGINEERING SDN BHD', $html);
        $this->assertStringContainsString('Page', $html);

        // 2.5's section title should not appear since include = false.
        $this->assertStringNotContainsString('2.5 ACTUAL WORK PROGRESS', $html);
    }

    public function test_export_pdf_endpoint_returns_a_pdf(): void
    {
        foreach (['reports.view', 'reports.manage', 'projects.view'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $user = User::create(['first_name' => 'V', 'last_name' => 'Wr', 'email' => 'vw-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $user->givePermissionTo(['reports.view']);

        $report = $this->makeReport();

        $response = $this->actingAs($user)->get("/api/monthly-reports/{$report->id}/export/pdf");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
