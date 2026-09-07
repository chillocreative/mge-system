<?php

namespace Tests\Feature\Uploads;

use App\Models\Attachment;
use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ciri 4 — bulk / whole-folder drawing upload into a contract, on the shared
 * upload engine. The strict drawing register (unique drawing_no) is untouched;
 * this is a separate folder store keyed to the contract.
 */
class ContractDrawingsTest extends TestCase
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

    public function test_it_bulk_uploads_a_folder_preserving_structure(): void
    {
        $contract = $this->contract();

        $this->actingAs($this->actor())
            ->postJson("/api/project-contracts/{$contract->id}/drawings", [
                'files' => [
                    UploadedFile::fake()->create('A-101.pdf', 20),
                    UploadedFile::fake()->create('S-201.pdf', 20),
                ],
                'paths' => [
                    'Drawings/Architectural/A-101.pdf',
                    'Drawings/Structural/S-201.pdf',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.uploaded', 2);

        $folders = Attachment::where('attachable_id', $contract->id)->pluck('folder_path')->sort()->values()->all();
        $this->assertSame(['Drawings/Architectural', 'Drawings/Structural'], $folders);
    }

    public function test_it_lists_drawings_for_a_contract(): void
    {
        $contract = $this->contract();
        $this->actingAs($this->actor())
            ->postJson("/api/project-contracts/{$contract->id}/drawings", [
                'files' => [UploadedFile::fake()->create('A-101.pdf', 10)],
                'paths' => ['Arch/A-101.pdf'],
            ])->assertOk();

        $res = $this->actingAs($this->actor())
            ->getJson("/api/project-contracts/{$contract->id}/drawings")
            ->assertOk();

        $this->assertCount(1, $res->json('data'));
        $this->assertSame('A-101.pdf', $res->json('data.0.name'));
        $this->assertSame('Arch', $res->json('data.0.folder'));
    }

    public function test_re_uploading_the_same_file_is_skipped(): void
    {
        $contract = $this->contract();
        $actor = $this->actor();

        $payload = fn () => [
            'files' => [UploadedFile::fake()->createWithContent('A-101.pdf', 'same')],
            'paths' => ['Arch/A-101.pdf'],
        ];

        $this->actingAs($actor)->postJson("/api/project-contracts/{$contract->id}/drawings", $payload())->assertJsonPath('data.uploaded', 1);
        $this->actingAs($actor)->postJson("/api/project-contracts/{$contract->id}/drawings", $payload())->assertJsonPath('data.skipped', 1);

        $this->assertSame(1, Attachment::where('attachable_id', $contract->id)->count());
    }

    public function test_a_disallowed_extension_is_rejected(): void
    {
        $contract = $this->contract();

        $this->actingAs($this->actor())
            ->postJson("/api/project-contracts/{$contract->id}/drawings", [
                'files' => [UploadedFile::fake()->create('virus.exe', 10)],
            ])
            ->assertStatus(422);
    }

    public function test_a_viewer_without_edit_cannot_upload(): void
    {
        $contract = $this->contract();
        $viewer = User::create(['first_name' => 'V', 'last_name' => 'W', 'email' => 'v-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $viewer->givePermissionTo('projects.view');

        $this->actingAs($viewer)
            ->postJson("/api/project-contracts/{$contract->id}/drawings", [
                'files' => [UploadedFile::fake()->create('A-101.pdf', 10)],
            ])
            ->assertForbidden();
    }

    public function test_it_deletes_a_drawing(): void
    {
        $contract = $this->contract();
        $this->actingAs($this->actor())
            ->postJson("/api/project-contracts/{$contract->id}/drawings", ['files' => [UploadedFile::fake()->create('A.pdf', 5)]])
            ->assertOk();
        $att = Attachment::where('attachable_id', $contract->id)->sole();

        $this->actingAs($this->actor())
            ->deleteJson("/api/project-contracts/drawings/{$att->id}")
            ->assertOk();

        $this->assertSame(0, Attachment::count());
    }
}
