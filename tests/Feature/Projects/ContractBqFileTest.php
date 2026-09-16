<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ciri — BQ file upload/download/delete operations on project contracts.
 * Single file store per contract, not bulk like drawings.
 */
class ContractBqFileTest extends TestCase
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

    public function test_it_uploads_a_bq_file_and_it_appears_on_show(): void
    {
        $contract = $this->contract();

        $this->actingAs($this->actor())
            ->postJson("/api/project-contracts/{$contract->id}/bq-file", [
                'file' => UploadedFile::fake()->create('bq.pdf', 100, 'application/pdf'),
            ])
            ->assertOk();

        $res = $this->actingAs($this->actor())
            ->getJson("/api/project-contracts/{$contract->id}")
            ->assertOk();

        $this->assertSame('bq.pdf', $res->json('data.bq_file_name'));
    }

    public function test_uploading_again_replaces_the_previous_file(): void
    {
        $contract = $this->contract();

        // Upload first file
        $this->actingAs($this->actor())
            ->postJson("/api/project-contracts/{$contract->id}/bq-file", [
                'file' => UploadedFile::fake()->create('bq_v1.pdf', 100, 'application/pdf'),
            ])
            ->assertOk();

        // Capture old path
        $oldPath = ProjectContract::find($contract->id)->bq_file_path;

        // Upload second file
        $this->actingAs($this->actor())
            ->postJson("/api/project-contracts/{$contract->id}/bq-file", [
                'file' => UploadedFile::fake()->create('bq_v2.pdf', 100, 'application/pdf'),
            ])
            ->assertOk();

        // Assert old file no longer exists
        Storage::disk('local')->assertMissing($oldPath);

        // Re-fetch model to get new path
        $newPath = ProjectContract::find($contract->id)->bq_file_path;
        $this->assertStringNotContainsString('v1', $newPath);
        Storage::disk('local')->assertExists($newPath);
    }

    public function test_viewing_the_bq_file_returns_inline_content_disposition(): void
    {
        $contract = $this->contract();

        // Upload PDF
        $this->actingAs($this->actor())
            ->postJson("/api/project-contracts/{$contract->id}/bq-file", [
                'file' => UploadedFile::fake()->create('bq.pdf', 100, 'application/pdf'),
            ])
            ->assertOk();

        // Get the BQ file endpoint
        $response = $this->actingAs($this->actor())
            ->get("/api/project-contracts/{$contract->id}/bq-file");
        $response->assertOk();
        $this->assertStringContainsStringIgnoringCase('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_deleting_removes_the_file_and_nulls_the_columns(): void
    {
        $contract = $this->contract();

        // Upload PDF
        $this->actingAs($this->actor())
            ->postJson("/api/project-contracts/{$contract->id}/bq-file", [
                'file' => UploadedFile::fake()->create('bq.pdf', 100, 'application/pdf'),
            ])
            ->assertOk();

        // Capture path
        $path = ProjectContract::find($contract->id)->bq_file_path;

        // Delete
        $this->actingAs($this->actor())
            ->deleteJson("/api/project-contracts/{$contract->id}/bq-file")
            ->assertOk();

        // Assert storage missing
        Storage::disk('local')->assertMissing($path);

        // Re-fetch contract and assert columns are null
        $contract = ProjectContract::find($contract->id);
        $this->assertNull($contract->bq_file_path);
        $this->assertNull($contract->bq_file_name);
    }

    public function test_a_disallowed_extension_is_rejected(): void
    {
        $contract = $this->contract();

        $this->actingAs($this->actor())
            ->postJson("/api/project-contracts/{$contract->id}/bq-file", [
                'file' => UploadedFile::fake()->create('virus.exe', 10),
            ])
            ->assertStatus(422);
    }

    public function test_a_viewer_without_edit_permission_cannot_upload(): void
    {
        $contract = $this->contract();
        $viewer = User::create(['first_name' => 'V', 'last_name' => 'W', 'email' => 'v-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $viewer->givePermissionTo('projects.view');

        $this->actingAs($viewer)
            ->postJson("/api/project-contracts/{$contract->id}/bq-file", [
                'file' => UploadedFile::fake()->create('bq.pdf', 100),
            ])
            ->assertForbidden();
    }
}
