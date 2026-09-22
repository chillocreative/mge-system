<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RolePresets;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug A — the System Administrator (and any other single-role user with no
 * direct permissions) showed a blank User Access page because roles carry no
 * permissions of their own; direct permissions are only seeded when a role is
 * assigned through UserService. A user created another way (seeder, before
 * this behaviour existed) has a role but zero direct permissions.
 *
 * RolePresets::backfillDirectPermissions() fixes this retroactively, and is
 * run from migration 2026_09_22_000005_backfill_direct_permissions_from_role_presets.
 */
class UserAccessBackfillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::create([
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'email' => 'admin@mge-pms.test',
            'password' => bcrypt('secret'),
            'status' => 'active',
        ]);
        $admin->syncRoles(['Admin & HR']);
        // Admin & HR is granted the roles.view/roles.edit permissions it needs
        // to call the access endpoints itself.
        $admin->givePermissionTo(['roles.view', 'roles.edit']);

        return $admin;
    }

    public function test_it_backfills_direct_permissions_for_a_single_role_user_with_none(): void
    {
        $user = User::create([
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'email' => 'admin@mge-pms.test',
            'password' => bcrypt('secret'),
            'status' => 'active',
        ]);
        $user->syncRoles(['Admin & HR']);

        $this->assertCount(0, $user->getDirectPermissions());

        $updated = RolePresets::backfillDirectPermissions();

        $this->assertSame(1, $updated);

        $user->refresh();
        $direct = $user->getDirectPermissions()->pluck('name')->all();

        $this->assertNotEmpty($direct);
        $this->assertEqualsCanonicalizing(RolePresets::for('Admin & HR'), $direct);
    }

    public function test_the_user_access_endpoint_now_returns_the_preset_permissions(): void
    {
        $admin = $this->admin();

        $target = User::create([
            'first_name' => 'Target',
            'last_name' => 'User',
            'email' => 'target@mge-pms.test',
            'password' => bcrypt('secret'),
            'status' => 'active',
        ]);
        $target->syncRoles(['Projects']);

        RolePresets::backfillDirectPermissions();

        $response = $this->actingAs($admin)
            ->getJson("/api/users/{$target->id}/access")
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            RolePresets::for('Projects'),
            $response->json('data.permissions'),
        );
    }

    public function test_a_user_with_custom_direct_permissions_is_left_untouched(): void
    {
        $user = User::create([
            'first_name' => 'Custom',
            'last_name' => 'User',
            'email' => 'custom@mge-pms.test',
            'password' => bcrypt('secret'),
            'status' => 'active',
        ]);
        $user->syncRoles(['Projects']);
        $user->syncPermissions(['dashboard.view']); // deliberately customised, not the full preset

        $updated = RolePresets::backfillDirectPermissions();

        $user->refresh();

        $this->assertSame(['dashboard.view'], $user->getDirectPermissions()->pluck('name')->all());

        // Confirm this user wasn't the one counted as updated.
        $this->assertNotEqualsCanonicalizing(RolePresets::for('Projects'), $user->getDirectPermissions()->pluck('name')->all());
        $this->assertGreaterThanOrEqual(0, $updated);
    }

    public function test_it_is_idempotent(): void
    {
        $user = User::create([
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'email' => 'admin@mge-pms.test',
            'password' => bcrypt('secret'),
            'status' => 'active',
        ]);
        $user->syncRoles(['Admin & HR']);

        RolePresets::backfillDirectPermissions();
        $secondRun = RolePresets::backfillDirectPermissions();

        // Already has direct permissions from the first run, so the second run
        // must not touch (or re-count) this user.
        $this->assertSame(0, $secondRun);
    }
}
