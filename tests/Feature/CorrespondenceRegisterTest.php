<?php

namespace Tests\Feature;

use App\Models\CorrespondenceType;
use App\Models\Project;
use App\Models\ProjectCorrespondence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CorrespondenceRegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('projects.view', 'web');
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
        $u->givePermissionTo('projects.view');

        return $u;
    }

    private function project(): Project
    {
        return Project::create([
            'name' => 'Test Project',
            'code' => 'PR'.random_int(1000, 9999),
            'status' => 'in_progress',
        ]);
    }

    private function type(): CorrespondenceType
    {
        return CorrespondenceType::firstOrCreate(
            ['code' => 'rfwi'],
            ['name' => 'RFWI', 'full_name' => 'Request For Work Inspection', 'color' => 'blue', 'sort_order' => 1, 'is_active' => true],
        );
    }

    private function correspondence(Project $project, string $type, array $overrides = []): ProjectCorrespondence
    {
        return ProjectCorrespondence::create(array_merge([
            'project_id' => $project->id,
            'type' => $type,
            'reference_no' => 'REF-'.random_int(1000, 9999),
            'title' => 'Sample title',
            'status' => 'open',
            'raised_date' => now()->subDays(5),
        ], $overrides));
    }

    public function test_register_returns_rows_in_raised_date_order_for_requested_type_only(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->type();

        $this->correspondence($project, 'rfwi', ['raised_date' => now()->subDays(2), 'title' => 'Second']);
        $this->correspondence($project, 'rfwi', ['raised_date' => now()->subDays(10), 'title' => 'First']);
        $this->correspondence($project, 'rfi', ['raised_date' => now()->subDays(1), 'title' => 'Other type']);

        $response = $this->actingAs($actor)->getJson("/api/correspondence/register?project_id={$project->id}&type=rfwi");

        $response->assertOk();
        $rows = $response->json('data.rows');
        $this->assertCount(2, $rows);
        $this->assertSame(1, $rows[0]['bil']);
        $this->assertSame('First', $rows[0]['title']);
        $this->assertSame(2, $rows[1]['bil']);
        $this->assertSame('Second', $rows[1]['title']);
    }

    public function test_date_closed_falls_back_to_consultant_closed_date(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->type();

        $this->correspondence($project, 'rfwi', [
            'actual_close_date' => null,
            'consultant_closed_date' => '2026-01-15',
            'client_closed_date' => '2026-01-20',
        ]);

        $response = $this->actingAs($actor)->getJson("/api/correspondence/register?project_id={$project->id}&type=rfwi");

        $response->assertOk();
        $response->assertJsonPath('data.rows.0.date_closed', '2026-01-15');
    }

    public function test_export_xlsx_returns_spreadsheet(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->type();
        $this->correspondence($project, 'rfwi');

        $response = $this->actingAs($actor)->get("/api/correspondence/register/export?project_id={$project->id}&type=rfwi&format=xlsx");

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
    }

    public function test_export_filename_survives_slashes_in_project_code(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $project->update(['code' => 'JPS/IP/BPB/10/2025']);
        $this->type();
        $this->correspondence($project, 'rfwi');

        $response = $this->actingAs($actor)->get("/api/correspondence/register/export?project_id={$project->id}&type=rfwi&format=xlsx");

        $response->assertOk();
        $this->assertStringContainsString('JPS-IP-BPB-10-2025-RFWI-register-', $response->headers->get('Content-Disposition'));
    }

    public function test_export_pdf_returns_pdf(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->type();
        $this->correspondence($project, 'rfwi');

        $response = $this->actingAs($actor)->get("/api/correspondence/register/export?project_id={$project->id}&type=rfwi&format=pdf");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_missing_project_id_is_rejected(): void
    {
        $actor = $this->actor();
        $this->type();

        $response = $this->actingAs($actor)->getJson('/api/correspondence/register?type=rfwi');

        $response->assertStatus(422);
    }
}
