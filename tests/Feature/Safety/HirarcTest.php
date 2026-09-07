<?php

namespace Tests\Feature\Safety;

use App\Models\HirarcAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HirarcTest extends TestCase
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

    public function test_creating_a_hirarc_computes_rating_and_level_per_item(): void
    {
        $res = $this->actingAs($this->actor(['safety.create']))
            ->postJson('/api/safety/hirarc', [
                'title' => 'Excavation works',
                'items' => [
                    ['hazard' => 'Cave-in', 'likelihood' => 3, 'severity' => 5],  // 15 -> critical
                    ['hazard' => 'Trip', 'likelihood' => 2, 'severity' => 2],       // 4 -> medium
                ],
            ])
            ->assertCreated();

        $items = collect($res->json('data.items'));
        $caveIn = $items->firstWhere('hazard', 'Cave-in');
        $this->assertSame(15, $caveIn['risk_rating']);
        $this->assertSame('critical', $caveIn['risk_level']);
        $trip = $items->firstWhere('hazard', 'Trip');
        $this->assertSame(4, $trip['risk_rating']);
        $this->assertSame('medium', $trip['risk_level']);
    }

    public function test_the_stored_rating_cannot_be_spoofed_from_the_payload(): void
    {
        // Even if a client sends a bogus rating/level, the server recomputes.
        $res = $this->actingAs($this->actor(['safety.create']))
            ->postJson('/api/safety/hirarc', [
                'title' => 'X',
                'items' => [['hazard' => 'H', 'likelihood' => 1, 'severity' => 1, 'risk_rating' => 99, 'risk_level' => 'critical']],
            ])
            ->assertCreated();

        $item = collect($res->json('data.items'))->first();
        $this->assertSame(1, $item['risk_rating']);
        $this->assertSame('low', $item['risk_level']);
    }

    public function test_update_replaces_items(): void
    {
        $actor = $this->actor(['safety.create', 'safety.manage']);
        $created = $this->actingAs($actor)->postJson('/api/safety/hirarc', [
            'title' => 'X', 'items' => [['hazard' => 'Old', 'likelihood' => 1, 'severity' => 1]],
        ])->json('data.id');

        $this->actingAs($actor)->putJson("/api/safety/hirarc/{$created}", [
            'items' => [['hazard' => 'New', 'likelihood' => 4, 'severity' => 4]],
        ])->assertOk();

        $items = HirarcAssessment::find($created)->items;
        $this->assertCount(1, $items);
        $this->assertSame('New', $items->first()->hazard);
        $this->assertSame(16, $items->first()->risk_rating);
    }

    public function test_delete_archives_rather_than_removes(): void
    {
        $actor = $this->actor(['safety.create', 'safety.manage']);
        $id = $this->actingAs($actor)->postJson('/api/safety/hirarc', ['title' => 'X'])->json('data.id');

        $this->actingAs($actor)->deleteJson("/api/safety/hirarc/{$id}")->assertOk();

        $this->assertDatabaseHas('hirarc_assessments', ['id' => $id, 'status' => 'archived']);
    }

    public function test_a_viewer_cannot_create(): void
    {
        $this->actingAs($this->actor(['safety.view']))
            ->postJson('/api/safety/hirarc', ['title' => 'X'])
            ->assertForbidden();
    }
}
