<?php

namespace Tests\Feature\MonthlyReport;

use App\Models\MonthlyReport;
use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\ProjectParty;
use App\Models\ProjectProgressPeriod;
use App\Models\User;
use App\Services\MonthlyReport\Export\Docx\DocxExporter;
use App\Services\MonthlyReport\MonthlyReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
