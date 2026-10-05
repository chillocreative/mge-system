<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\User;
use Database\Seeders\ProjectExpenseMaterialsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MaterialsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('projects.view', 'web');
        Permission::findOrCreate('projects.edit', 'web');
    }

    private function actor(bool $editor = true): User
    {
        $user = User::create(['first_name' => 'M', 'last_name' => 'User', 'email' => uniqid().'@test.local', 'password' => bcrypt('x'), 'status' => 'active']);
        $user->givePermissionTo($editor ? ['projects.view', 'projects.edit'] : ['projects.view']);

        return $user;
    }

    private function expense(array $overrides = []): array
    {
        $project = Project::create(['name' => 'P', 'code' => uniqid('P'), 'status' => 'in_progress']);

        return array_merge(['project_id' => $project->id, 'expense_date' => '2026-10-01', 'category' => 'Materials', 'description' => 'Sand', 'quantity' => 2, 'unit_price' => 10], $overrides);
    }

    public function test_material_crud_search_uniqueness_and_active_filter(): void
    {
        $actor = $this->actor();
        $created = $this->actingAs($actor)->postJson('/api/materials', ['category' => ' Materials ', 'description' => ' Sand '])
            ->assertCreated()->assertJsonPath('data.category', 'Materials')->assertJsonPath('data.description', 'Sand');
        $id = $created->json('data.id');
        $this->actingAs($actor)->postJson('/api/materials', ['category' => 'Materials', 'description' => 'Sand'])->assertUnprocessable();
        $this->actingAs($actor)->postJson('/api/materials', ['category' => 'Materials', 'description' => 'Gravel'])->assertCreated();
        $this->actingAs($actor)->getJson('/api/materials?search=Sand')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($actor)->putJson("/api/materials/{$id}", ['description' => 'Gravel'])->assertUnprocessable();
        $this->actingAs($actor)->putJson("/api/materials/{$id}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->actingAs($actor)->getJson('/api/materials?active_only=1')->assertOk()->assertJsonCount(1, 'data');
        $viewer = $this->actor(false);
        $this->actingAs($viewer)->getJson('/api/materials')->assertOk();
        $this->actingAs($viewer)->postJson('/api/materials', ['category' => 'X', 'description' => 'Y'])->assertForbidden();
    }

    public function test_manual_expenses_require_an_active_matching_pair_but_legacy_rows_can_be_edited(): void
    {
        $actor = $this->actor();
        $sand = Material::create(['category' => 'Materials', 'description' => 'Sand']);
        Material::create(['category' => 'Equipment', 'description' => 'Crane']);
        $payload = $this->expense();
        $this->actingAs($actor)->postJson('/api/project-finance/expenses', array_merge($payload, ['description' => 'Crane']))->assertUnprocessable();
        $created = $this->actingAs($actor)->postJson('/api/project-finance/expenses', $payload)->assertCreated()->assertJsonPath('data.amount', '20.00');
        $id = $created->json('data.id');
        $sand->update(['is_active' => false]);
        $this->actingAs($actor)->postJson('/api/project-finance/expenses', $payload)->assertUnprocessable();
        $this->actingAs($actor)->putJson("/api/project-finance/expenses/{$id}", ['expense_date' => '2026-10-01', 'quantity' => 3, 'unit_price' => 10])->assertOk()->assertJsonPath('data.amount', '30.00');
        $this->actingAs($actor)->putJson("/api/project-finance/expenses/{$id}", ['expense_date' => '2026-10-01', 'category' => 'Equipment', 'quantity' => 3, 'unit_price' => 10])->assertUnprocessable();
        $legacy = ProjectExpense::create($payload + ['amount' => 20]);
        $this->actingAs($actor)->putJson("/api/project-finance/expenses/{$legacy->id}", ['expense_date' => '2026-10-01', 'quantity' => 4, 'unit_price' => 10])->assertOk();
    }

    public function test_backfill_is_idempotent_and_does_not_change_expenses(): void
    {
        $payload = $this->expense();
        ProjectExpense::create($payload + ['amount' => 20]);
        ProjectExpense::create($payload + ['amount' => 20]);
        (new ProjectExpenseMaterialsSeeder)->run();
        (new ProjectExpenseMaterialsSeeder)->run();
        $this->assertDatabaseCount('materials', 1);
        $this->assertDatabaseCount('project_expenses', 2);
        $this->assertDatabaseHas('materials', ['category' => 'Materials', 'description' => 'Sand', 'is_active' => true]);
    }
}
