<?php

namespace Tests\Feature;

use App\Models\CorrespondenceType;
use App\Models\Project;
use App\Models\SiteLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Project codes and reference numbers contain "/" (production's project is "JPS/IP/BPB/10/2025").
 * A slash in the download filename made these two PDFs fail with a 500 every time.
 */
class PdfDownloadFilenameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
    }

    private function actor(): User
    {
        $u = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => uniqid().'@test.local', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->givePermissionTo(['projects.view', 'projects.edit']);

        return $u;
    }

    public function test_site_log_monthly_pdf_downloads_when_the_project_code_contains_slashes(): void
    {
        $project = Project::create(['name' => 'Flood Mitigation', 'code' => 'JPS/IP/BPB/10/2025', 'status' => 'in_progress']);
        $actor = $this->actor();
        SiteLog::create(['project_id' => $project->id, 'logged_by' => $actor->id, 'log_date' => '2026-10-27', 'work_performed' => 'Jet grouting']);

        $response = $this->actingAs($actor)->get("/api/projects/{$project->id}/site-logs/report/pdf?month=2026-10");

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('site-log-report-JPS-IP-BPB-10-2025-2026-10.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_site_log_monthly_pdf_still_uses_plain_project_codes_unchanged(): void
    {
        $project = Project::create(['name' => 'Plain', 'code' => 'PR1234', 'status' => 'in_progress']);

        $response = $this->actingAs($this->actor())->get("/api/projects/{$project->id}/site-logs/report/pdf?month=2026-10");

        $response->assertOk();
        $this->assertStringContainsString('site-log-report-PR1234-2026-10.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_correspondence_pdf_downloads_when_the_reference_number_contains_slashes(): void
    {
        $actor = $this->actor();
        $project = Project::create(['name' => 'P', 'code' => 'PR'.random_int(1000, 9999), 'status' => 'in_progress']);
        CorrespondenceType::firstOrCreate(
            ['code' => 'rfwi'],
            ['name' => 'RFWI', 'full_name' => 'Request For Work Inspection', 'color' => 'blue', 'sort_order' => 1, 'is_active' => true],
        );

        $id = $this->actingAs($actor)->postJson('/api/correspondence', [
            'project_id' => $project->id, 'type' => 'rfwi', 'title' => 'Inspection', 'raised_date' => '2026-10-27',
            'reference_no' => 'MGE/MANUAL/RFWI-001',
        ])->assertCreated()->json('data.id');

        $response = $this->actingAs($actor)->get("/api/correspondence/{$id}/pdf");

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('correspondence-MGE-MANUAL-RFWI-001.pdf', $response->headers->get('Content-Disposition'));
    }
}
