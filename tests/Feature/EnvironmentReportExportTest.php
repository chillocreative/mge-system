<?php

namespace Tests\Feature;

use App\Models\EnvironmentProjectSetting;
use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\ProjectParty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EnvironmentReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('environmental.view', 'web');
        Permission::findOrCreate('environmental.create', 'web');
        Permission::findOrCreate('environmental.manage', 'web');
    }

    private function actor(): User
    {
        $u = User::create([
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'u-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('x'),
            'status' => 'active',
        ]);
        $u->givePermissionTo(['environmental.view', 'environmental.create', 'environmental.manage']);

        return $u;
    }

    private function project(?string $code = null): Project
    {
        return Project::create([
            'name' => 'River Project',
            'code' => $code ?? 'PR'.random_int(1000, 9999),
            'status' => 'in_progress',
            'start_date' => '2025-11-01',
            'end_date' => '2027-10-31',
        ]);
    }

    private function contract(Project $project): ProjectContract
    {
        return ProjectContract::create([
            'project_id' => $project->id,
            'title' => 'Main Contract',
            'contract_no' => 'JPS/IP/BPB/10/2025',
            'contract_value' => 288000000,
            'is_main' => true,
            'contract_sum' => 288000000,
            'duration_months' => 24,
            'dlp_months' => 12,
            'possession_date' => '2025-10-31',
            'completion_date' => '2027-10-31',
            'dlp_start_date' => '2027-11-01',
            'dlp_end_date' => '2028-11-01',
            'start_date' => '2025-10-31',
            'end_date' => '2027-10-31',
        ]);
    }

    private function seedParties(Project $project): void
    {
        ProjectParty::create(['project_id' => $project->id, 'name' => 'Owner Sdn Bhd', 'address' => "Level 1\nJalan Owner", 'report_role' => 'owner', 'sort_order' => 1]);
        ProjectParty::create(['project_id' => $project->id, 'name' => 'SO Department', 'address' => "Level 2\nJalan SO", 'report_role' => 'superintending_officer', 'sort_order' => 2]);
        ProjectParty::create(['project_id' => $project->id, 'name' => 'Lukman Rosli Resources', 'address' => 'Jalan Consultant', 'report_role' => 'consultant', 'sort_order' => 3]);
        ProjectParty::create(['project_id' => $project->id, 'name' => 'Multi Green Engineering Sdn Bhd', 'address' => 'Jalan Contractor', 'report_role' => 'contractor', 'sort_order' => 4]);
    }

    private function seedSettings(Project $project): void
    {
        EnvironmentProjectSetting::create([
            'project_id' => $project->id,
            'consultant_company' => 'Lukman Rosli Resources',
            'consultant_name' => 'Mohamad Lukman Bin Rosli',
            'consultant_reg_no' => 'CePEOEIA/00001',
            'officer_name' => 'Noralina Binti Rosli',
            'officer_reg_no' => 'TBA',
        ]);
    }

    private function createReport(User $actor, Project $project): int
    {
        return $this->actingAs($actor)->postJson('/api/environment/reports', [
            'project_id' => $project->id,
            'period_start' => '2025-12-15',
            'period_end' => '2026-01-15',
        ])->json('data.id');
    }

    public function test_export_pdf_download_and_inline(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->contract($project);
        $this->seedParties($project);
        $this->seedSettings($project);
        $reportId = $this->createReport($actor, $project);

        $download = $this->actingAs($actor)->get("/api/environment/reports/{$reportId}/export/pdf");
        $download->assertOk();
        $this->assertSame('application/pdf', $download->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $download->headers->get('Content-Disposition'));

        $bytes = $download->baseResponse->getContent();
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertGreaterThan(5000, strlen($bytes));

        $inline = $this->actingAs($actor)->get("/api/environment/reports/{$reportId}/export/pdf?inline=1");
        $inline->assertOk();
        $this->assertSame('application/pdf', $inline->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', $inline->headers->get('Content-Disposition'));
    }

    public function test_export_docx(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->contract($project);
        $this->seedParties($project);
        $this->seedSettings($project);
        $reportId = $this->createReport($actor, $project);

        $response = $this->actingAs($actor)->get("/api/environment/reports/{$reportId}/export/docx");
        $response->assertOk();
        $this->assertStringContainsString('wordprocessingml', $response->headers->get('Content-Type'));
    }

    public function test_export_pdf_with_bmp_photo_asset(): void
    {
        Storage::fake('local');

        $actor = $this->actor();
        $project = $this->project();
        $this->contract($project);
        $this->seedParties($project);
        $this->seedSettings($project);
        $reportId = $this->createReport($actor, $project);

        $file = UploadedFile::fake()->image('wash-trough.jpg', 40, 40);
        $this->actingAs($actor)->postJson("/api/environment/reports/{$reportId}/assets", [
            'kind' => 'bmp_photo',
            'caption' => 'Wash Trough',
            'file' => $file,
        ])->assertCreated();

        $response = $this->actingAs($actor)->get("/api/environment/reports/{$reportId}/export/pdf");
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_export_filename_sanitised_when_project_code_has_slash(): void
    {
        $actor = $this->actor();
        $project = $this->project('PR/2026/001');
        $this->contract($project);
        $this->seedParties($project);
        $this->seedSettings($project);
        $reportId = $this->createReport($actor, $project);

        $response = $this->actingAs($actor)->get("/api/environment/reports/{$reportId}/export/pdf");
        $response->assertOk();

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringNotContainsString('PR/2026/001', $disposition);
        $this->assertStringContainsString('PR-2026-001-Environment-Report-No1.pdf', $disposition);
    }
}
