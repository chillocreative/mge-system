<?php

namespace Tests\Feature\Users;

use App\Models\User;
use App\Support\RolePresets;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProtegeRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_protege_is_available_from_roles_api_and_has_employee_level_permissions(): void
    {
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin-'.uniqid().'@mge-eng.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $admin->givePermissionTo('roles.view');

        $response = $this->actingAs($admin)->getJson('/api/roles')->assertOk();

        $roles = collect($response->json('data'));
        $this->assertSame(1, $roles->where('name', 'Protege')->count());
        $this->assertSame(RolePresets::for('Employee'), RolePresets::for('Protege'));
        $this->assertSame(['dashboard.view', 'leave.view', 'leave.request', 'training.request', 'calendar.view', 'memos.view'], RolePresets::for('Protege'));
    }

    public function test_seeding_again_does_not_duplicate_protege_or_change_existing_users(): void
    {
        $user = User::create([
            'first_name' => 'Existing',
            'last_name' => 'Protege',
            'email' => 'existing-'.uniqid().'@mge-eng.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->syncRoles(['Protege']);
        $user->givePermissionTo('calendar.view');

        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(1, Role::query()->where('name', 'Protege')->where('guard_name', 'web')->count());
        $this->assertSame(['Protege'], $user->fresh()->getRoleNames()->all());
        $this->assertSame(['calendar.view'], $user->fresh()->getDirectPermissions()->pluck('name')->all());
    }
}
