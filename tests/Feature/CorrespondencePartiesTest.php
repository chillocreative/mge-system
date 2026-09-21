<?php

namespace Tests\Feature;

use App\Models\CorrespondenceType;
use App\Models\Project;
use App\Models\ProjectCorrespondence;
use App\Models\ProjectParty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CorrespondencePartiesTest extends TestCase
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
        $u = User::create([
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'u-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('x'),
            'status' => 'active',
        ]);
        $u->givePermissionTo(['projects.view', 'projects.edit']);

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

    public function test_store_persists_from_and_to_party_and_show_returns_their_names(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->type();
        $mge = ProjectParty::create(['project_id' => $project->id, 'name' => 'MGESB', 'type' => 'contractor']);
        $client = ProjectParty::create(['project_id' => $project->id, 'name' => 'ABC Client', 'type' => 'client']);

        $response = $this->actingAs($actor)->postJson('/api/correspondence', [
            'project_id' => $project->id,
            'type' => 'rfwi',
            'title' => 'Test correspondence',
            'raised_date' => now()->toDateString(),
            'from_party_id' => $mge->id,
            'to_party_id' => $client->id,
        ]);

        $response->assertCreated();
        $id = $response->json('data.id');

        $this->assertDatabaseHas('project_correspondences', [
            'id' => $id,
            'from_party_id' => $mge->id,
            'to_party_id' => $client->id,
        ]);

        $show = $this->actingAs($actor)->getJson("/api/correspondence/{$id}");
        $show->assertOk();
        $this->assertSame('MGESB', $show->json('data.from_party.name'));
        $this->assertSame('ABC Client', $show->json('data.to_party.name'));
    }

    public function test_update_can_change_from_and_to_party(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->type();
        $client = ProjectParty::create(['project_id' => $project->id, 'name' => 'ABC Client', 'type' => 'client']);
        $consultant = ProjectParty::create(['project_id' => $project->id, 'name' => 'XYZ Consultant', 'type' => 'consultant']);

        $c = ProjectCorrespondence::create([
            'project_id' => $project->id,
            'type' => 'rfwi',
            'title' => 'Test correspondence',
            'status' => 'open',
            'raised_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($actor)->putJson("/api/correspondence/{$c->id}", [
            'from_party_id' => $client->id,
            'to_party_id' => $consultant->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('project_correspondences', [
            'id' => $c->id,
            'from_party_id' => $client->id,
            'to_party_id' => $consultant->id,
        ]);
    }

    public function test_from_party_id_that_does_not_exist_is_rejected(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->type();

        $response = $this->actingAs($actor)->postJson('/api/correspondence', [
            'project_id' => $project->id,
            'type' => 'rfwi',
            'title' => 'Test correspondence',
            'raised_date' => now()->toDateString(),
            'from_party_id' => 999999,
        ]);

        $response->assertStatus(422);
    }

    public function test_party_from_another_project_is_rejected(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $other = $this->project();
        $this->type();
        $foreign = ProjectParty::create(['project_id' => $other->id, 'name' => 'Other Co', 'type' => 'client']);

        $response = $this->actingAs($actor)->postJson('/api/correspondence', [
            'project_id' => $project->id,
            'type' => 'rfwi',
            'title' => 'Cross-project party',
            'raised_date' => now()->toDateString(),
            'to_party_id' => $foreign->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('to_party_id');
    }
}
