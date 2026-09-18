<?php

namespace Tests\Feature\MonthlyReport;

use App\Models\MonthlyReport;
use App\Models\MonthlyReportAsset;
use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\ProjectParty;
use App\Models\ProjectProgrammeVersion;
use App\Models\ProjectProgressPeriod;
use App\Models\ProjectScheduleBaseline;
use App\Models\SiteLog;
use App\Models\User;
use App\Services\MonthlyReport\Export\OrientationPlanner;
use App\Services\MonthlyReport\Export\PdfExporter;
use App\Services\MonthlyReport\Export\PdfMerger;
use App\Services\MonthlyReport\MonthlyReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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

    public function test_render_returns_merged_pdf_with_landscape_pages_and_global_numbering(): void
    {
        $report = $this->makeReportWithSeries();
        $bytes = app(PdfExporter::class)->render($report);

        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertMatchesRegularExpression('/MediaBox \[0 0 841\.\d+ 595\.\d+\]/', $bytes); // at least one landscape page
        $this->assertMatchesRegularExpression('/MediaBox \[0 0 595\.\d+ 841\.\d+\]/', $bytes); // and a portrait one
        $this->assertStringContainsString('Page 1 of ', $this->decodeFooterText($bytes));
    }

    /**
     * PdfMerger's content streams are FlateDecode-compressed by default, so footer text is
     * not directly greppable in the raw PDF bytes. Inflate every stream and pull the Tj
     * operator's text out of the decompressed content.
     */
    private function decodeFooterText(string $pdf): string
    {
        preg_match_all('/\/Filter\s*\/FlateDecode.*?stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams, PREG_SET_ORDER);

        $text = '';
        foreach ($streams as $stream) {
            $decoded = @gzuncompress($stream[1]);
            if ($decoded === false) {
                continue;
            }

            preg_match_all('/\((.*?)\)\s*Tj/s', $decoded, $ms, PREG_SET_ORDER);
            foreach ($ms as $m) {
                $text .= $m[1].' ';
            }
        }

        return $text;
    }

    public function test_render_throws_a_validation_exception_when_no_sections_are_included(): void
    {
        $report = $this->makeReport();
        $report->sections()->update(['include' => false]);

        $this->expectException(ValidationException::class);

        app(PdfExporter::class)->render($report->fresh(['sections', 'project', 'period']));
    }

    public function test_work_programme_section_renders_caption_task_and_indent(): void
    {
        $report = $this->makeReport();
        $report->sections()->where('key', '2.5')->update(['include' => true]);

        $version = ProjectProgrammeVersion::create([
            'project_id' => $report->project_id, 'label' => 'Baseline', 'status_date' => '2026-01-15',
            'source_type' => 'manual', 'is_current' => true, 'activity_count' => 3,
        ]);
        $version->activities()->create([
            'seq' => 1, 'outline_level' => 1, 'name' => 'Earthworks', 'is_summary' => true,
        ]);
        $version->activities()->create([
            'seq' => 2, 'outline_level' => 2, 'name' => 'Excavation', 'duration_days' => 5,
            'start' => '2026-01-02', 'finish' => '2026-01-06', 'actual_pct' => 45, 'plan_pct' => 50, 'is_summary' => false,
        ]);
        $version->activities()->create([
            'seq' => 3, 'outline_level' => 2, 'name' => 'Backfilling', 'duration_days' => 12,
            'start' => '2026-01-07', 'finish' => '2026-01-18', 'actual_pct' => 0, 'plan_pct' => 100, 'is_summary' => false,
        ]);

        app(MonthlyReportService::class)->regenerate($report, '2.5');

        $html = app(PdfExporter::class)->html($report->fresh(['sections', 'project', 'period']));

        $this->assertStringContainsString('Programme: Baseline (status date 15/01/2026)', $html);
        $this->assertStringContainsString('Excavation', $html);
        $this->assertStringContainsString('padding-left', $html);
    }

    public function test_gantt_pages_are_inserted_immediately_after_the_chunk_containing_2_5(): void
    {
        Storage::fake('local');
        $report = $this->makeReport();
        $report->sections()->where('key', '2.5')->update(['include' => true]);

        $path = "monthly-reports/{$report->id}/assets/gantt.pdf";
        Storage::disk('local')->put($path, \Barryvdh\DomPDF\Facade\Pdf::loadHTML('<p>GANTT-PAGE</p>')->output());
        MonthlyReportAsset::create([
            'report_id' => $report->id, 'kind' => 'gantt_page', 'file_path' => $path,
            'file_name' => 'gantt.pdf', 'extension' => 'pdf', 'sort_order' => 1,
        ]);

        $recorder = new class extends PdfMerger
        {
            /** @var array<int, array{pdf?: string, file?: string, label?: string}> */
            public array $recordedParts = [];

            public function merge(array $parts, string $footerLeft): string
            {
                $this->recordedParts = $parts;

                return '%PDF-FAKE';
            }
        };
        $this->app->instance(PdfMerger::class, $recorder);

        $report = $report->fresh(['sections', 'project', 'period']);
        app(PdfExporter::class)->render($report);

        $includedKeys = $report->sections->where('include', true)->sortBy('sort_order')->pluck('key')->values()->all();
        $chunks = OrientationPlanner::plan($includedKeys, $report->options['landscape_sections'] ?? null);
        $chunkIndexWith25 = collect($chunks)->search(fn ($chunk) => in_array('2.5', $chunk['keys'], true));

        $this->assertNotFalse($chunkIndexWith25, 'Expected a chunk containing section 2.5.');
        $this->assertArrayNotHasKey('label', $recorder->recordedParts[$chunkIndexWith25], 'The 2.5 chunk itself is a rendered PDF part, not a labelled asset part.');
        $this->assertArrayHasKey('label', $recorder->recordedParts[$chunkIndexWith25 + 1], 'The Gantt asset must immediately follow the chunk containing 2.5.');
        $this->assertSame('gantt.pdf', $recorder->recordedParts[$chunkIndexWith25 + 1]['label']);
    }
}
