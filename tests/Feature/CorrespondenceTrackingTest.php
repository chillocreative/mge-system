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

class CorrespondenceTrackingTest extends TestCase
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

    public function test_store_persists_tracking_columns(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->type();

        $response = $this->actingAs($actor)->postJson('/api/correspondence', [
            'project_id' => $project->id,
            'type' => 'rfwi',
            'title' => 'Test correspondence',
            'raised_date' => now()->toDateString(),
            'reminded_date' => now()->addDays(3)->toDateString(),
            'consultant_status' => 'Pending',
            'client_status' => 'Replied',
            'consultant_closed_date' => now()->addDays(5)->toDateString(),
            'client_closed_date' => now()->addDays(6)->toDateString(),
        ]);

        $response->assertCreated();
        $id = $response->json('data.id');

        $this->assertSame('Pending', $response->json('data.consultant_status'));
        $this->assertSame('Replied', $response->json('data.client_status'));
        $this->assertNotNull($response->json('data.reminded_date'));
        $this->assertNotNull($response->json('data.consultant_closed_date'));
        $this->assertNotNull($response->json('data.client_closed_date'));

        $this->assertDatabaseHas('project_correspondences', [
            'id' => $id,
            'consultant_status' => 'Pending',
            'client_status' => 'Replied',
        ]);
    }

    public function test_update_can_change_tracking_columns(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->type();

        $c = ProjectCorrespondence::create([
            'project_id' => $project->id,
            'type' => 'rfwi',
            'title' => 'Test correspondence',
            'status' => 'open',
            'raised_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($actor)->putJson("/api/correspondence/{$c->id}", [
            'reminded_date' => now()->addDay()->toDateString(),
            'consultant_status' => 'Forward to JPRiZ',
            'client_status' => 'Approved',
            'consultant_closed_date' => now()->addDays(2)->toDateString(),
            'client_closed_date' => now()->addDays(3)->toDateString(),
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('project_correspondences', [
            'id' => $c->id,
            'consultant_status' => 'Forward to JPRiZ',
            'client_status' => 'Approved',
        ]);
        $this->assertNotNull($c->fresh()->reminded_date);
        $this->assertNotNull($c->fresh()->consultant_closed_date);
        $this->assertNotNull($c->fresh()->client_closed_date);
    }

    public function test_consultant_status_over_60_chars_is_rejected(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->type();

        $response = $this->actingAs($actor)->postJson('/api/correspondence', [
            'project_id' => $project->id,
            'type' => 'rfwi',
            'title' => 'Test correspondence',
            'raised_date' => now()->toDateString(),
            'consultant_status' => str_repeat('a', 61),
        ]);

        $response->assertStatus(422);
    }

    public function test_store_and_update_persist_typed_details_reviews_and_links(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->type();
        $party = ProjectParty::create(['project_id' => $project->id, 'name' => 'JPRIZ', 'type' => 'consultant']);
        $target = ProjectCorrespondence::create([
            'project_id' => $project->id, 'type' => 'rfwi', 'title' => 'Earlier submission',
            'status' => 'open', 'raised_date' => now()->subDay()->toDateString(),
        ]);

        $response = $this->actingAs($actor)->postJson('/api/correspondence', [
            'project_id' => $project->id,
            'type' => 'rfwi',
            'title' => 'Inspection request',
            'raised_date' => now()->toDateString(),
            'detail' => [
                'work_scope' => 'Drainage works',
                'inspection_type' => 'Pre-pour inspection',
                'inspection_date' => now()->addDay()->toDateString(),
            ],
            'party_reviews' => [[
                'party_role' => 'jpriz',
                'project_party_id' => $party->id,
                'status_raw' => 'APPROVED',
                'closed_date' => now()->addDays(2)->toDateString(),
            ]],
            'links' => [[
                'target_correspondence_id' => $target->id,
                'relation_type' => 'resubmission_of',
                'note' => 'Updated submission',
            ]],
        ])->assertCreated();

        $id = $response->json('data.id');
        $this->assertNotEmpty($response->json('data.reference_no'));
        $this->assertDatabaseHas('correspondence_details', ['project_correspondence_id' => $id, 'work_scope' => 'Drainage works']);
        $this->assertDatabaseHas('correspondence_party_reviews', [
            'project_correspondence_id' => $id, 'party_role' => 'jpriz',
            'status_raw' => 'APPROVED', 'status_normalized' => 'approved',
        ]);
        $this->assertDatabaseHas('correspondence_links', [
            'source_correspondence_id' => $id, 'target_correspondence_id' => $target->id,
            'relation_type' => 'resubmission_of',
        ]);

        $this->actingAs($actor)->putJson("/api/correspondence/{$id}", [
            'reference_no' => 'MGE/MANUAL/RFWI-001',
            'detail' => ['work_scope' => 'Revised drainage works'],
            'party_reviews' => [[
                'party_role' => 'jps', 'status_raw' => 'RECEIVED',
            ]],
            'links_sync' => true,
        ])->assertOk();

        $this->assertDatabaseHas('project_correspondences', [
            'id' => $id, 'reference_no' => 'MGE/MANUAL/RFWI-001', 'reference_no_is_manual' => true,
        ]);
        $this->assertDatabaseHas('correspondence_party_reviews', ['project_correspondence_id' => $id, 'party_role' => 'jps']);
        $this->assertDatabaseMissing('correspondence_links', ['source_correspondence_id' => $id]);
    }

    public function test_close_date_endpoint_updates_legacy_columns_and_matching_party_reviews(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->type();
        $correspondence = ProjectCorrespondence::create([
            'project_id' => $project->id,
            'type' => 'rfwi',
            'title' => 'Inspection request',
            'status' => 'open',
            'raised_date' => now()->toDateString(),
        ]);
        $correspondence->partyReviews()->create([
            'party_role' => 'jpriz',
            'status_raw' => 'APPROVED',
            'status_normalized' => 'approved',
        ]);

        $date = now()->addDay()->toDateString();
        $this->actingAs($actor)
            ->patchJson("/api/correspondence/{$correspondence->id}/close-dates", [
                'consultant_closed_date' => $date,
            ])
            ->assertOk()
            ->assertJsonPath('data.consultant_closed_date', $date)
            ->assertJsonPath('data.party_reviews.0.closed_date', $date);

        $this->assertDatabaseHas('correspondence_party_reviews', [
            'project_correspondence_id' => $correspondence->id,
            'party_role' => 'jpriz',
            'closed_date' => $date,
        ]);
    }
}
