<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DirectReportingTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'user-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ], $overrides));
    }

    private function hrActor(): User
    {
        Permission::findOrCreate('staff.edit', 'web');
        $actor = $this->user(['first_name' => 'HR']);
        $actor->givePermissionTo('staff.edit');

        return $actor;
    }

    public function test_hr_can_assign_and_clear_an_active_direct_reporting_user(): void
    {
        $actor = $this->hrActor();
        $staff = $this->user(['first_name' => 'Staff']);
        $manager = $this->user(['first_name' => 'Manager']);

        $this->actingAs($actor)
            ->patchJson("/api/users/{$staff->id}/direct-reporting", ['reports_to_id' => $manager->id])
            ->assertOk()
            ->assertJsonPath('data.reports_to_id', $manager->id)
            ->assertJsonPath('data.reports_to.full_name', $manager->full_name);

        $this->assertSame($manager->id, $staff->fresh()->reports_to_id);

        $this->actingAs($actor)
            ->patchJson("/api/users/{$staff->id}/direct-reporting", ['reports_to_id' => null])
            ->assertOk()
            ->assertJsonPath('data.reports_to_id', null)
            ->assertJsonPath('data.reports_to', null);
    }

    public function test_direct_reporting_target_must_be_active_and_cannot_be_self(): void
    {
        $actor = $this->hrActor();
        $staff = $this->user(['first_name' => 'Staff']);
        $inactive = $this->user(['status' => 'inactive']);

        $this->actingAs($actor)
            ->patchJson("/api/users/{$staff->id}/direct-reporting", ['reports_to_id' => $inactive->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reports_to_id');

        $this->actingAs($actor)
            ->patchJson("/api/users/{$staff->id}/direct-reporting", ['reports_to_id' => $staff->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reports_to_id');
    }

    public function test_reporting_cycles_are_rejected(): void
    {
        $actor = $this->hrActor();
        $first = $this->user(['first_name' => 'First']);
        $second = $this->user(['first_name' => 'Second', 'reports_to_id' => $first->id]);

        $this->actingAs($actor)
            ->patchJson("/api/users/{$first->id}/direct-reporting", ['reports_to_id' => $second->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reports_to_id');
    }

    public function test_authenticated_profile_payload_includes_direct_reporting_person(): void
    {
        $manager = $this->user(['first_name' => 'Direct', 'last_name' => 'Manager']);
        $staff = $this->user(['reports_to_id' => $manager->id]);

        $this->actingAs($staff)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.reports_to_id', $manager->id)
            ->assertJsonPath('data.reports_to.full_name', 'Direct Manager');
    }

    public function test_user_cannot_manage_their_own_direct_reporting_relationship(): void
    {
        Permission::findOrCreate('staff.edit', 'web');
        $currentManager = $this->user(['first_name' => 'Current']);
        $otherManager = $this->user(['first_name' => 'Other']);
        $staff = $this->user(['reports_to_id' => $currentManager->id]);

        $this->actingAs($staff)
            ->patchJson("/api/users/{$staff->id}/direct-reporting", ['reports_to_id' => $otherManager->id])
            ->assertForbidden();

        $this->actingAs($staff)
            ->putJson('/api/profile', [
                'full_name' => $staff->full_name,
                'email' => $staff->email,
                'reports_to_id' => $otherManager->id,
            ])
            ->assertOk();

        $this->assertSame($currentManager->id, $staff->fresh()->reports_to_id);
    }
}
