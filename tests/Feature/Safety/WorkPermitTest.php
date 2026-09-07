<?php

namespace Tests\Feature\Safety;

use App\Models\Project;
use App\Models\ProjectSite;
use App\Models\User;
use App\Models\WorkPermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkPermitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['safety.view', 'safety.create', 'safety.manage'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
    }

    private function actor(array $perms): User
    {
        $u = User::create(['first_name' => 'S', 'last_name' => 'O', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->givePermissionTo($perms);

        return $u;
    }

    private function payload(array $over = []): array
    {
        return array_merge([
            'title' => 'Hot work on tank',
            'type' => 'hot_work',
            'valid_from' => now()->toDateTimeString(),
            'valid_to' => now()->addDay()->toDateTimeString(),
        ], $over);
    }

    public function test_a_new_permit_gets_a_sequential_number_and_starts_as_draft(): void
    {
        $res = $this->actingAs($this->actor(['safety.create']))
            ->postJson('/api/safety/permits', $this->payload())
            ->assertCreated();

        $this->assertSame('draft', $res->json('data.status'));
        $this->assertSame('PTW-'.now()->year.'-0001', $res->json('data.permit_no'));
    }

    public function test_the_full_happy_path_draft_to_approved_to_closed(): void
    {
        $requester = $this->actor(['safety.create']);
        $manager = $this->actor(['safety.manage']);

        $id = $this->actingAs($requester)->postJson('/api/safety/permits', $this->payload())->json('data.id');

        $this->actingAs($requester)->postJson("/api/safety/permits/{$id}/submit")->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->actingAs($manager)->postJson("/api/safety/permits/{$id}/approve", ['decision_notes' => 'ok'])->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->actingAs($manager)->postJson("/api/safety/permits/{$id}/close")->assertOk()
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_you_cannot_approve_a_permit_that_is_not_pending(): void
    {
        $manager = $this->actor(['safety.manage', 'safety.create']);
        $id = $this->actingAs($manager)->postJson('/api/safety/permits', $this->payload())->json('data.id');

        // still a draft, not submitted
        $this->actingAs($manager)->postJson("/api/safety/permits/{$id}/approve")->assertStatus(422);
    }

    public function test_you_cannot_close_a_permit_that_was_never_approved(): void
    {
        $manager = $this->actor(['safety.manage', 'safety.create']);
        $id = $this->actingAs($manager)->postJson('/api/safety/permits', $this->payload())->json('data.id');
        $this->actingAs($manager)->postJson("/api/safety/permits/{$id}/submit");

        $this->actingAs($manager)->postJson("/api/safety/permits/{$id}/close")->assertStatus(422);
    }

    public function test_an_approved_permit_cannot_be_edited(): void
    {
        $manager = $this->actor(['safety.manage', 'safety.create']);
        $id = $this->actingAs($manager)->postJson('/api/safety/permits', $this->payload())->json('data.id');
        $this->actingAs($manager)->postJson("/api/safety/permits/{$id}/submit");
        $this->actingAs($manager)->postJson("/api/safety/permits/{$id}/approve");

        $this->actingAs($manager)->putJson("/api/safety/permits/{$id}", ['title' => 'Changed'])->assertStatus(422);
    }

    public function test_an_approved_permit_past_its_window_reads_as_expired(): void
    {
        $manager = $this->actor(['safety.manage', 'safety.create', 'safety.view']);
        $id = $this->actingAs($manager)->postJson('/api/safety/permits', $this->payload([
            'valid_from' => now()->subDays(2)->toDateTimeString(),
            'valid_to' => now()->subDay()->toDateTimeString(),
        ]))->json('data.id');
        $this->actingAs($manager)->postJson("/api/safety/permits/{$id}/submit");
        $this->actingAs($manager)->postJson("/api/safety/permits/{$id}/approve");

        $res = $this->actingAs($manager)->getJson("/api/safety/permits/{$id}")->assertOk();
        $this->assertSame('approved', $res->json('data.status'));
        $this->assertSame('expired', $res->json('data.effective_status'));
    }

    public function test_a_view_only_user_cannot_create_or_approve(): void
    {
        $viewer = $this->actor(['safety.view']);
        $this->actingAs($viewer)->postJson('/api/safety/permits', $this->payload())->assertForbidden();

        $permit = WorkPermit::create($this->payload(['requested_by' => $viewer->id, 'status' => 'pending', 'permit_no' => 'PTW-X-1']));
        $this->actingAs($viewer)->postJson("/api/safety/permits/{$permit->id}/approve")->assertForbidden();
    }

    public function test_a_site_from_another_project_is_rejected(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'PW'.random_int(1000, 9999), 'status' => 'in_progress']);
        $foreignSite = ProjectSite::create(['project_id' => Project::create(['name' => 'Q', 'code' => 'QW'.random_int(1000, 9999), 'status' => 'in_progress'])->id, 'name' => 'Foreign']);

        $this->actingAs($this->actor(['safety.create']))
            ->postJson('/api/safety/permits', $this->payload(['project_id' => $project->id, 'site_id' => $foreignSite->id]))
            ->assertStatus(422);
    }
}
