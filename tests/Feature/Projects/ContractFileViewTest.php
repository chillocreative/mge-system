<?php

namespace Tests\Feature\Projects;

use App\Models\Attachment;
use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\ProjectContractFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ciri — Inline file viewing for project contract files and drawings.
 * Tests the new /view endpoints that return inline responses instead of downloads.
 */
class ContractFileViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
    }

    private function actor(): User
    {
        $u = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->givePermissionTo(['projects.view', 'projects.edit']);

        return $u;
    }

    private function contract(): ProjectContract
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);

        return ProjectContract::create(['project_id' => $project->id, 'title' => 'Main Contract', 'status' => 'active']);
    }

    public function test_it_views_an_uploaded_document_inline(): void
    {
        $contract = $this->contract();

        // Upload a PDF document
        $this->actingAs($this->actor())
            ->postJson("/api/project-contracts/{$contract->id}/files", [
                'files' => [
                    UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'files' => [
                        '*' => ['id', 'file_name'],
                    ],
                ],
            ]);

        // Get the uploaded file id
        $fileId = $this->actingAs($this->actor())
            ->getJson("/api/project-contracts/{$contract->id}")
            ->assertOk()
            ->json('data.files.0.id');

        // View the file inline
        $response = $this->actingAs($this->actor())
            ->get("/api/project-contracts/files/{$fileId}/view");

        $response->assertStatus(200);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsStringIgnoringCase('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_viewing_a_document_missing_from_disk_returns_404(): void
    {
        $contract = $this->contract();

        // Upload a PDF document
        $this->actingAs($this->actor())
            ->postJson("/api/project-contracts/{$contract->id}/files", [
                'files' => [
                    UploadedFile::fake()->create('missing.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertOk();

        // Get the file id and path
        $file = ProjectContractFile::where('project_contract_id', $contract->id)->sole();
        $fileId = $file->id;
        $path = $file->file_path;

        // Delete from fake storage
        Storage::disk('local')->delete($path);

        // Try to view the file - should return 404
        $response = $this->actingAs($this->actor())
            ->get("/api/project-contracts/files/{$fileId}/view");

        $response->assertStatus(404);
    }

    public function test_it_views_a_drawing_inline(): void
    {
        $contract = $this->contract();

        // Upload a drawing folder
        $this->actingAs($this->actor())
            ->postJson("/api/project-contracts/{$contract->id}/drawings", [
                'files' => [
                    UploadedFile::fake()->create('A-101.pdf', 20),
                ],
                'paths' => [
                    'Drawings/A-101.pdf',
                ],
            ])
            ->assertOk();

        // Get the created attachment
        $attachment = Attachment::where('attachable_type', $contract->getMorphClass())
            ->where('attachable_id', $contract->id)
            ->sole();

        $attachmentId = $attachment->id;

        // View the drawing inline
        $response = $this->actingAs($this->actor())
            ->get("/api/project-contracts/drawings/{$attachmentId}/view");

        $response->assertStatus(200);
        $this->assertStringContainsStringIgnoringCase('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_unauthenticated_user_cannot_view_a_document(): void
    {
        $contract = $this->contract();

        // Upload a PDF document as authenticated actor
        $this->actingAs($this->actor())
            ->postJson("/api/project-contracts/{$contract->id}/files", [
                'files' => [
                    UploadedFile::fake()->create('secret.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertOk();

        // Get the file id
        $fileId = $this->actingAs($this->actor())
            ->getJson("/api/project-contracts/{$contract->id}")
            ->assertOk()
            ->json('data.files.0.id');

        // actingAs() leaves the resolved user bound for the rest of the test;
        // forget the resolved guards so this request is genuinely unauthenticated
        // (the sanctum guard is a RequestGuard and has no logout() method).
        $this->app['auth']->forgetGuards();

        $response = $this->getJson("/api/project-contracts/files/{$fileId}/view");

        $response->assertUnauthorized();
    }
}
