<?php

namespace Tests\Feature\MonthlyReport;

use App\Models\MonthlyReport;
use App\Models\MonthlyReportAsset;
use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\ProjectParty;
use App\Models\ProjectProgressPeriod;
use App\Models\ProjectResourceCategory;
use App\Models\ProjectScheduleBaseline;
use App\Models\SiteLog;
use App\Models\User;
use App\Services\MonthlyReport\Export\Docx\DocxDocument;
use App\Services\MonthlyReport\Export\Docx\DocxExporter;
use App\Services\MonthlyReport\Export\Docx\Writers\MatrixWriter;
use App\Services\MonthlyReport\Export\Docx\Writers\RowsWriter;
use App\Services\MonthlyReport\MonthlyReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DocxExportTest extends TestCase
{
    use RefreshDatabase;

    /** Copied from PdfExportTest::makeReport() — same fixture, not shared by inheritance. */
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

    public function test_docx_export_contains_cover_toc_and_row_sections(): void
    {
        $report = $this->makeReport()->fresh(['sections', 'project', 'period']);

        $bytes = app(DocxExporter::class)->render($report);

        $this->assertStringStartsWith('PK', $bytes);

        $xml = $this->documentXml($bytes);

        $this->assertStringContainsString('RTB SG. MUAR', $xml);
        $this->assertStringContainsString('TOC \o', $xml);
        $this->assertStringContainsString('1.1 PROJECT INFORMATION', $xml);
        $this->assertStringContainsString('MULTI GREEN ENGINEERING SDN BHD', $xml);

        $footerXml = $this->anyPartXmlMatching($bytes, '#^word/footer\d+\.xml$#');
        $this->assertStringContainsString('NUMPAGES', $footerXml);
    }

    public function test_docx_export_renders_special_sections(): void
    {
        Storage::fake('local');

        $project = Project::create(['name' => 'Special Sections Test', 'code' => 'SST-'.uniqid(), 'status' => 'in_progress']);

        ProjectScheduleBaseline::create(['project_id' => $project->id, 'month' => '2025-12-01', 'scheduled_physical_pct' => 1, 'scheduled_financial_amount' => 100000, 'scheduled_financial_pct' => 1]);
        ProjectScheduleBaseline::create(['project_id' => $project->id, 'month' => '2026-01-01', 'scheduled_physical_pct' => 2, 'scheduled_financial_amount' => 200000, 'scheduled_financial_pct' => 2]);

        $previousPeriod = ProjectProgressPeriod::create(['project_id' => $project->id, 'period_no' => 1, 'period_start' => '2025-11-16', 'period_end' => '2025-12-15', 'physical_scheduled_pct' => 1, 'physical_actual_pct' => 1, 'financial_scheduled_pct' => 1, 'financial_actual_pct' => 1]);
        $period = ProjectProgressPeriod::create(['project_id' => $project->id, 'period_no' => 2, 'period_start' => '2025-12-16', 'period_end' => '2025-12-18', 'planning_days_completion' => 100, 'physical_scheduled_pct' => 2, 'physical_actual_pct' => 4, 'financial_scheduled_pct' => 2, 'financial_actual_pct' => 4, 'financial_actual_amount' => 200000]);

        ProjectResourceCategory::create(['project_id' => $project->id, 'kind' => 'worker', 'group' => 'Tradesman', 'name' => 'General Worker', 'sort_order' => 0, 'active' => true]);

        $user = User::create(['first_name' => 'Site', 'last_name' => 'Logger', 'email' => 'site-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);

        $log = SiteLog::create(['project_id' => $project->id, 'log_date' => '2025-12-16', 'title' => 'Day 1', 'logged_by' => $user->id]);
        $log->workers()->create(['worker_type' => 'General Worker', 'count' => 20]);
        $log->weatherEvents()->createMany([
            ['condition' => 'rain_start', 'event_time' => '09:00'],
            ['condition' => 'rain_stop', 'event_time' => '10:30'],
        ]);

        /** @var MonthlyReportService $service */
        $service = app(MonthlyReportService::class);
        $report = $service->create($project->id, ['period_id' => $period->id], $user->id);

        $png = $this->tinyPng();
        $chartPath = "monthly-reports/{$report->id}/assets/chart.png";
        Storage::disk('local')->put($chartPath, $png);
        MonthlyReportAsset::create([
            'report_id' => $report->id,
            'kind' => 'chart_physical_scurve',
            'file_path' => $chartPath,
            'file_name' => 'chart.png',
            'extension' => 'png',
            'size' => strlen($png),
            'pages' => 1,
            'sort_order' => 0,
        ]);

        $report = $report->fresh(['sections', 'project', 'period']);

        $bytes = app(DocxExporter::class)->render($report);
        $xml = $this->documentXml($bytes);

        $this->assertStringContainsString('2.1', $xml);
        $this->assertStringContainsString('Previous', $xml);
        $this->assertStringContainsString('Current', $xml);
        $this->assertStringContainsString('Dec-25', $xml); // 2.2 month label
        $this->assertStringContainsString('w:fill="60A5FA"', $xml);
        $this->assertStringContainsString('General Worker', $xml);
        $this->assertStringContainsString('<w:pict>', $xml); // 2.2 chart asset embedded (PHPWord renders images as VML, not <w:drawing>)
        $this->assertStringContainsString('Chart not captured', $xml); // 2.4 has no chart asset

        // 4.3's 24 hour cells must stay blank when unfilled, not render as a wall of '-' dashes
        // (only the Hrs column may legitimately show '-' for a dry day).
        $weatherRegion = $this->regionBetween($xml, '4.3 WEATHER REPORT', '5.0 PROGRESS PHOTOGRAPH');
        $this->assertLessThan(24, substr_count($weatherRegion, '>-<'));
    }

    public function test_docx_export_survives_a_corrupt_chart_asset(): void
    {
        Storage::fake('local');

        $project = Project::create(['name' => 'Corrupt Chart Test', 'code' => 'CCT-'.uniqid(), 'status' => 'in_progress']);

        ProjectScheduleBaseline::create(['project_id' => $project->id, 'month' => '2025-12-01', 'scheduled_physical_pct' => 1, 'scheduled_financial_amount' => 100000, 'scheduled_financial_pct' => 1]);
        ProjectScheduleBaseline::create(['project_id' => $project->id, 'month' => '2026-01-01', 'scheduled_physical_pct' => 2, 'scheduled_financial_amount' => 200000, 'scheduled_financial_pct' => 2]);

        $period = ProjectProgressPeriod::create(['project_id' => $project->id, 'period_no' => 1, 'period_start' => '2025-12-16', 'period_end' => '2026-01-15', 'physical_scheduled_pct' => 1, 'physical_actual_pct' => 1, 'financial_scheduled_pct' => 1, 'financial_actual_pct' => 1]);

        $user = User::create(['first_name' => 'Corrupt', 'last_name' => 'Chart', 'email' => 'cc-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);

        /** @var MonthlyReportService $service */
        $service = app(MonthlyReportService::class);
        $report = $service->create($project->id, ['period_id' => $period->id], $user->id);

        $garbage = 'this is not a valid image file, just garbage bytes truncated mid-way';
        $chartPath = "monthly-reports/{$report->id}/assets/chart.png";
        Storage::disk('local')->put($chartPath, $garbage);
        MonthlyReportAsset::create([
            'report_id' => $report->id,
            'kind' => 'chart_physical_scurve',
            'file_path' => $chartPath,
            'file_name' => 'chart.png',
            'extension' => 'png',
            'size' => strlen($garbage),
            'pages' => 1,
            'sort_order' => 0,
        ]);

        $before = glob(sys_get_temp_dir().'/docximg*');

        $report = $report->fresh(['sections', 'project', 'period']);
        $bytes = app(DocxExporter::class)->render($report);
        $xml = $this->documentXml($bytes);

        $this->assertStringStartsWith('PK', $bytes, 'export must still produce a valid DOCX despite the corrupt image');
        $this->assertStringContainsString('Image could not be embedded.', $xml);

        $after = glob(sys_get_temp_dir().'/docximg*');
        $this->assertSame($before, $after, 'no leaked docximg* temp files after a garbage image asset');
    }

    public function test_finalised_report_docx_export_returns_a_valid_docx(): void
    {
        $report = $this->makeReport();

        app(MonthlyReportService::class)->finalise($report, (int) $report->generated_by);

        $report = $report->fresh(['sections', 'project', 'period']);
        $this->assertSame(MonthlyReport::STATUS_FINAL, $report->status);

        $bytes = app(DocxExporter::class)->render($report);

        $this->assertStringStartsWith('PK', $bytes);
    }

    public function test_gantt_landscape_image_section_leaves_no_empty_page_before_the_next_section(): void
    {
        Storage::fake('local');

        $report = $this->makeReport();
        $report->sections()->where('key', '2.5')->update(['include' => true]);

        $png = $this->tinyPng();
        $path = "monthly-reports/{$report->id}/assets/gantt.png";
        Storage::disk('local')->put($path, $png);
        MonthlyReportAsset::create([
            'report_id' => $report->id,
            'kind' => 'gantt_page',
            'file_path' => $path,
            'file_name' => 'gantt.png',
            'extension' => 'png',
            'size' => strlen($png),
            'pages' => 1,
            'sort_order' => 0,
        ]);

        $report = $report->fresh(['sections', 'project', 'period']);
        $bytes = app(DocxExporter::class)->render($report);
        $xml = $this->documentXml($bytes);

        $this->assertStringContainsString('2.6 NOTICE OF DELAY', $xml, 'the section after the Gantt image must still be written');
        $this->assertNoEmptySectionBodies($xml);
    }

    public function test_gantt_writer_reports_pdf_only_attachment_when_no_image_pages(): void
    {
        Storage::fake('local');

        $report = $this->makeReport();
        $report->sections()->where('key', '2.5')->update(['include' => true]);

        $pdf = '%PDF-1.4 fake pdf bytes';
        $path = "monthly-reports/{$report->id}/assets/gantt.pdf";
        Storage::disk('local')->put($path, $pdf);
        MonthlyReportAsset::create([
            'report_id' => $report->id,
            'kind' => 'gantt_page',
            'file_path' => $path,
            'file_name' => 'gantt.pdf',
            'extension' => 'pdf',
            'size' => strlen($pdf),
            'pages' => 3,
            'sort_order' => 0,
        ]);

        $report = $report->fresh(['sections', 'project', 'period']);
        $bytes = app(DocxExporter::class)->render($report);
        $xml = $this->documentXml($bytes);

        $this->assertStringContainsString('The work programme (Gantt chart) PDF is attached separately (see the PDF export).', $xml);
        $this->assertStringNotContainsString('is attached on the following', $xml);
    }

    public function test_2_3_money_columns_are_right_aligned(): void
    {
        $data = ['rows' => [[
            'ipc_no' => '1', 'submission_date' => '-', 'evaluation_date' => '-',
            'claim_amount' => 1000.5, 'certified' => 900, 'wjp_current' => 1, 'wjp_cumulative' => 1,
            'paid_current' => 1, 'paid_cumulative' => 1, 'remarks' => 'Paid',
        ]]];

        $doc = new DocxDocument(['title' => 'Align test']);
        $doc->newSection('portrait');
        (new RowsWriter)->write($doc, '2.3', $data, null, []);
        $xml = $this->documentXml($doc->save());

        $this->assertStringContainsString('w:val="end"', $xml);
    }

    /**
     * Splits document.xml on each <w:sectPr>...</w:sectPr> boundary and asserts every resulting
     * chunk (one per section body) contains at least one real paragraph run or table — i.e. no
     * section in the body is entirely empty.
     */
    private function assertNoEmptySectionBodies(string $xml): void
    {
        $chunks = preg_split('/<w:sectPr\b.*?<\/w:sectPr>/s', $xml);
        $sectPrCount = substr_count($xml, '<w:sectPr');
        $this->assertGreaterThan(1, $sectPrCount, 'expected multiple sections in the fixture');

        foreach ($chunks as $i => $chunk) {
            if ($i === count($chunks) - 1 && trim(strip_tags($chunk)) === '') {
                continue; // tail after the final sectPr, normally empty
            }

            $hasContent = (bool) preg_match('/<w:t[ >]|<w:tbl>|<w:pict>|<w:drawing>/', $chunk);
            $this->assertTrue($hasContent, "Section body #{$i} between sectPr boundaries is empty");
        }
    }

    public function test_matrix_totals_are_computed_from_rows_not_a_stale_totals_field(): void
    {
        $data = [
            'schema' => 1,
            'days' => [
                ['date' => '2025-12-16', 'label' => '16', 'month' => 'Dec-25'],
                ['date' => '2025-12-17', 'label' => '17', 'month' => 'Dec-25'],
            ],
            'rows' => [
                ['no' => 1, 'description' => 'Excavator', 'counts' => [3, 5]],
                ['no' => 2, 'description' => 'Crane', 'counts' => [1, 0]],
            ],
            // Deliberately wrong/stale — the writer must ignore this and sum the rows above
            // (3+1=4, 5+0=5), the same rule as s4-1.blade.php's own $totals computation.
            'totals' => [999, 999],
        ];

        $doc = new DocxDocument(['title' => 'Matrix totals test']);
        $doc->newSection('portrait');
        (new MatrixWriter)->write($doc, '4.2', $data, null, ['orientation' => 'portrait']);
        $xml = $this->documentXml($doc->save());

        $this->assertStringContainsString('>4<', $xml);
        $this->assertStringContainsString('>5<', $xml);
        $this->assertStringNotContainsString('>999<', $xml);
    }

    /** Substring of $xml starting at $startMarker and ending just before $endMarker. */
    private function regionBetween(string $xml, string $startMarker, string $endMarker): string
    {
        $start = strpos($xml, $startMarker);
        $this->assertNotFalse($start, "Marker not found: {$startMarker}");
        $end = strpos($xml, $endMarker, $start);
        $this->assertNotFalse($end, "Marker not found: {$endMarker}");

        return substr($xml, $start, $end - $start);
    }

    private function tinyPng(): string
    {
        if (function_exists('imagecreatetruecolor')) {
            $img = imagecreatetruecolor(1, 1);
            ob_start();
            imagepng($img);
            $bytes = ob_get_clean();
            imagedestroy($img);

            return $bytes;
        }

        // Minimal valid 1x1 transparent PNG.
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }

    public function test_export_docx_endpoint_returns_a_docx(): void
    {
        foreach (['reports.view', 'reports.manage', 'projects.view'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $user = User::create(['first_name' => 'V', 'last_name' => 'Wr', 'email' => 'vw-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $user->givePermissionTo(['reports.view']);

        $report = $this->makeReport();

        $response = $this->actingAs($user)->get("/api/monthly-reports/{$report->id}/export/docx");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->assertStringStartsWith('PK', $response->getContent());
    }

    public function test_export_docx_is_reachable_by_a_view_only_user(): void
    {
        foreach (['reports.view', 'reports.manage'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $user = User::create(['first_name' => 'View', 'last_name' => 'Only', 'email' => 'view-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $user->givePermissionTo(['reports.view']);

        $report = $this->makeReport();

        $response = $this->actingAs($user)->get("/api/monthly-reports/{$report->id}/export/docx");

        $response->assertOk();
    }

    public function test_export_docx_requires_authentication(): void
    {
        $report = $this->makeReport();

        $response = $this->get("/api/monthly-reports/{$report->id}/export/docx");

        $response->assertUnauthorized();
    }

    private function documentXml(string $bytes): string
    {
        return $this->partXml($bytes, 'word/document.xml');
    }

    private function partXml(string $bytes, string $part): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($tmp, $bytes);
        $zip = new \ZipArchive;
        $zip->open($tmp);
        $xml = (string) $zip->getFromName($part);
        $zip->close();
        unlink($tmp);

        return $xml;
    }

    private function anyPartXmlMatching(string $bytes, string $pattern): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($tmp, $bytes);
        $zip = new \ZipArchive;
        $zip->open($tmp);

        $combined = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== null && preg_match($pattern, $name)) {
                $combined .= (string) $zip->getFromName($name);
            }
        }
        $zip->close();
        unlink($tmp);

        return $combined;
    }
}
