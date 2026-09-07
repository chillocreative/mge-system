<?php

namespace Tests\Feature\Correspondence;

use App\Models\CorrespondenceEvent;
use App\Models\Project;
use App\Models\ProjectCorrespondence;
use App\Models\ProjectCorrespondenceFile;
use App\Models\ProjectParty;
use App\Models\ProjectSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CorrespondenceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
    }

    private function user(array $perms): User
    {
        $u = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->givePermissionTo($perms);

        return $u;
    }

    private function correspondence(): array
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
        $c = ProjectCorrespondence::create([
            'project_id' => $project->id, 'type' => 'ncr', 'title' => 'Cracked slab',
            'status' => 'open', 'raised_date' => now()->toDateString(),
        ]);

        return [$project, $c];
    }

    public function test_handover_moves_the_current_party_and_records_an_event(): void
    {
        [$project, $c] = $this->correspondence();
        $client = ProjectParty::create(['project_id' => $project->id, 'name' => 'Client Co', 'type' => 'client']);

        $this->actingAs($this->user(['projects.view', 'projects.edit']))
            ->postJson("/api/correspondence/{$c->id}/handover", ['to_party_id' => $client->id, 'note' => 'over to you'])
            ->assertOk()
            ->assertJsonPath('data.current_party_id', $client->id);

        $this->assertDatabaseHas('correspondence_events', [
            'project_correspondence_id' => $c->id, 'event_type' => 'handed_over', 'to_party_id' => $client->id,
        ]);
    }

    public function test_a_correspondence_cannot_close_without_a_closing_reference(): void
    {
        [, $c] = $this->correspondence();
        ProjectCorrespondenceFile::create(['project_correspondence_id' => $c->id, 'file_path' => 'x/y.pdf', 'file_name' => 'y.pdf']);

        $this->actingAs($this->user(['projects.view', 'projects.edit']))
            ->postJson("/api/correspondence/{$c->id}/close", ['closing_reference' => ''])
            ->assertStatus(422);

        $this->assertSame('open', $c->fresh()->status);
    }

    public function test_a_correspondence_cannot_close_without_a_supporting_document(): void
    {
        [, $c] = $this->correspondence();

        $this->actingAs($this->user(['projects.view', 'projects.edit']))
            ->postJson("/api/correspondence/{$c->id}/close", ['closing_reference' => 'CLOSE-1'])
            ->assertStatus(422);

        $this->assertSame('open', $c->fresh()->status);
    }

    public function test_a_valid_close_stamps_the_actual_close_date_and_records_an_event(): void
    {
        [, $c] = $this->correspondence();
        ProjectCorrespondenceFile::create(['project_correspondence_id' => $c->id, 'file_path' => 'x/y.pdf', 'file_name' => 'y.pdf']);

        $this->actingAs($this->user(['projects.view', 'projects.edit']))
            ->postJson("/api/correspondence/{$c->id}/close", ['closing_reference' => 'CLOSE-1'])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $fresh = $c->fresh();
        $this->assertSame('CLOSE-1', $fresh->closing_reference);
        $this->assertNotNull($fresh->actual_close_date);
        $this->assertDatabaseHas('correspondence_events', ['project_correspondence_id' => $c->id, 'event_type' => 'closed']);
    }

    public function test_events_endpoint_returns_the_timeline(): void
    {
        [$project, $c] = $this->correspondence();
        $party = ProjectParty::create(['project_id' => $project->id, 'name' => 'Consultant', 'type' => 'consultant']);
        $actor = $this->user(['projects.view', 'projects.edit']);

        $this->actingAs($actor)->postJson("/api/correspondence/{$c->id}/note", ['note' => 'chased'])->assertOk();
        $this->actingAs($actor)->postJson("/api/correspondence/{$c->id}/handover", ['to_party_id' => $party->id])->assertOk();

        $res = $this->actingAs($actor)->getJson("/api/correspondence/{$c->id}/events")->assertOk();
        $this->assertCount(2, $res->json('data'));
    }

    public function test_pdf_export_renders(): void
    {
        [, $c] = $this->correspondence();
        CorrespondenceEvent::create(['project_correspondence_id' => $c->id, 'event_type' => 'raised', 'to_status' => 'open']);

        $res = $this->actingAs($this->user(['projects.view']))->get("/api/correspondence/{$c->id}/pdf");
        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
    }

    public function test_parties_are_scoped_to_a_project(): void
    {
        $p1 = Project::create(['name' => 'One', 'code' => 'ONE', 'status' => 'in_progress']);
        $p2 = Project::create(['name' => 'Two', 'code' => 'TWO', 'status' => 'in_progress']);
        ProjectParty::create(['project_id' => $p1->id, 'name' => 'A', 'type' => 'client']);
        ProjectParty::create(['project_id' => $p2->id, 'name' => 'B', 'type' => 'client']);

        $res = $this->actingAs($this->user(['projects.view']))
            ->getJson("/api/project-parties?project_id={$p1->id}")
            ->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('A', $res->json('data.0.name'));
    }

    public function test_a_correspondence_can_be_filed_under_its_projects_site_but_not_anothers(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'PC'.random_int(1000, 9999), 'status' => 'in_progress']);
        $site = ProjectSite::create(['project_id' => $project->id, 'name' => 'Zone 1']);
        $foreign = ProjectSite::create(['project_id' => Project::create(['name' => 'Q', 'code' => 'QC'.random_int(1000, 9999), 'status' => 'in_progress'])->id, 'name' => 'Foreign']);
        $editor = $this->user(['projects.view', 'projects.edit']);

        $ok = ['project_id' => $project->id, 'type' => 'ncr', 'title' => 'X', 'raised_date' => now()->toDateString()];

        $this->actingAs($editor)->postJson('/api/correspondence', array_merge($ok, ['site_id' => $site->id]))->assertCreated();
        $this->actingAs($editor)->postJson('/api/correspondence', array_merge($ok, ['site_id' => $foreign->id]))->assertStatus(422);
    }
}
