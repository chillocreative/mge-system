<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ContractCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['projects.view', 'projects.edit'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->actor = User::create([
            'first_name' => 'Contract',
            'last_name' => 'Editor',
            'email' => 'contract-editor-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->actor->givePermissionTo(['projects.view', 'projects.edit']);
        $this->project = Project::create([
            'name' => 'Category Project',
            'code' => 'CAT-'.uniqid(),
            'status' => 'in_progress',
        ]);
    }

    public function test_category_is_required_and_must_use_a_supported_slug(): void
    {
        $base = [
            'project_id' => $this->project->id,
            'title' => 'Categorised Contract',
        ];

        $this->actingAs($this->actor)
            ->postJson('/api/project-contracts', $base)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');

        $this->actingAs($this->actor)
            ->postJson('/api/project-contracts', $base + ['category' => 'other'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');
    }

    public function test_category_is_stored_with_its_label_and_can_filter_the_list(): void
    {
        $created = $this->actingAs($this->actor)
            ->postJson('/api/project-contracts', [
                'project_id' => $this->project->id,
                'title' => 'Client Contract',
                'category' => 'client',
            ])
            ->assertCreated();

        $this->assertSame('client', $created->json('data.category'));
        $this->assertSame('MGE dengan Client', $created->json('data.category_label'));

        ProjectContract::create([
            'project_id' => $this->project->id,
            'title' => 'Vendor Contract',
            'category' => 'vendor_third_party',
        ]);

        $response = $this->actingAs($this->actor)
            ->getJson('/api/project-contracts?category=client')
            ->assertOk();

        $this->assertSame(['Client Contract'], collect($response->json('data.data'))->pluck('title')->all());
    }

    public function test_existing_contracts_may_remain_uncategorised_until_edited(): void
    {
        $contract = ProjectContract::create([
            'project_id' => $this->project->id,
            'title' => 'Legacy Contract',
        ]);

        $this->assertNull($contract->category);
        $this->assertNull($contract->category_label);
    }
}
