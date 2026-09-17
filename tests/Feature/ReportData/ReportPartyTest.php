<?php

namespace Tests\Feature\ReportData;

use App\Models\Project;
use App\Models\ProjectParty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportPartyTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->editor = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $this->editor->givePermissionTo(['projects.view', 'projects.edit']);
    }

    private function project(): Project
    {
        return Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
    }

    public function test_party_with_report_role_and_contacts_is_created_and_contacts_are_replaced_on_update(): void
    {
        $project = $this->project();

        $res = $this->actingAs($this->editor)->postJson("/api/projects/{$project->id}/parties", [
            'name' => 'Jurutera Perunding Riz Sdn. Bhd.',
            'type' => 'consultant',
            'report_role' => 'consultant',
            'address' => '73-2, Petaling Utama Avenue',
            'contacts' => [
                ['name' => 'Ir. Khairul Izman Bin Kamal', 'phone' => '+60 12-222 8429'],
                ['name' => 'Ir. Muhammad Hazwan bin Hamzah', 'designation' => 'Resident Engineer'],
            ],
        ])->assertCreated();
        $id = $res->json('data.id');
        $this->assertCount(2, $res->json('data.contacts'));
        $this->assertSame('consultant', ProjectParty::find($id)->report_role);

        $this->actingAs($this->editor)->putJson("/api/projects/{$project->id}/parties/{$id}", [
            'contacts' => [['name' => 'Only One']],
        ])->assertOk();
        $this->assertSame(1, ProjectParty::find($id)->contacts()->count());
    }

    public function test_existing_correspondence_parties_still_work_without_report_fields(): void
    {
        $project = $this->project();
        $party = ProjectParty::create(['project_id' => $project->id, 'name' => 'JPS', 'type' => 'client']);

        $this->assertNull($party->fresh()->report_role);
        $list = $this->actingAs($this->editor)->getJson("/api/projects/{$project->id}/parties")->assertOk();
        $this->assertSame('JPS', $list->json('data.0.name'));
        $this->assertSame([], $list->json('data.0.contacts'));
    }

    public function test_role_other_requires_a_label_and_parties_are_scoped_to_project(): void
    {
        $p1 = $this->project();
        $p2 = $this->project();
        $this->actingAs($this->editor)->postJson("/api/projects/{$p1->id}/parties", ['name' => 'X', 'report_role' => 'other'])->assertStatus(422);
        $party = ProjectParty::create(['project_id' => $p1->id, 'name' => 'JPS', 'type' => 'client']);

        $this->actingAs($this->editor)->deleteJson("/api/projects/{$p2->id}/parties/{$party->id}")->assertNotFound();
    }

    public function test_logo_upload_and_inline_view(): void
    {
        Storage::fake('local');
        $project = $this->project();
        $party = ProjectParty::create(['project_id' => $project->id, 'name' => 'MGE', 'type' => 'main_contractor']);

        $this->actingAs($this->editor)->post("/api/projects/{$project->id}/parties/{$party->id}/logo", [
            'logo' => UploadedFile::fake()->create('mge.png', 20, 'image/png'),
        ])->assertOk();

        $this->assertNotNull($party->fresh()->logo_path);
        Storage::disk('local')->assertExists($party->fresh()->logo_path);

        $res = $this->actingAs($this->editor)->get("/api/projects/{$project->id}/parties/{$party->id}/logo");
        $res->assertOk();
        $this->assertSame('image/png', $res->headers->get('content-type'));
    }
}
