<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\WaterQualityRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WaterQualityRecordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('environmental.view', 'web');
        Permission::findOrCreate('environmental.create', 'web');
        Permission::findOrCreate('environmental.manage', 'web');
    }

    private function actor(array $permissions = ['environmental.view', 'environmental.create', 'environmental.manage']): User
    {
        $u = User::create([
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'u-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('x'),
            'status' => 'active',
        ]);
        $u->givePermissionTo($permissions);

        return $u;
    }

    private function project(): Project
    {
        return Project::create([
            'name' => 'River Project',
            'code' => 'PR'.random_int(1000, 9999),
            'status' => 'in_progress',
        ]);
    }

    public function test_store_persists_conditions_and_insitu_json(): void
    {
        $actor = $this->actor();
        $project = $this->project();

        $response = $this->actingAs($actor)->postJson('/api/environment/water-quality', [
            'project_id' => $project->id,
            'sample_date' => '2026-09-10',
            'sample_time' => '09:30',
            'data_collector' => 'Ali',
            'witness' => 'Ah Meng',
            'conditions' => [
                'W1' => ['odour' => 'No', 'floating' => 'No', 'area' => 'Forest', 'flow' => 'Slow', 'colour' => 'Clear', 'level' => 'Shallow', 'weather' => 'Sunny'],
            ],
            'insitu' => [
                'W1' => ['temperature' => '27.5', 'ph' => '7.1', 'do' => '5.2'],
            ],
        ]);

        $response->assertCreated();
        $id = $response->json('data.id');

        $record = WaterQualityRecord::findOrFail($id);
        $this->assertSame('No', $record->conditions['W1']['odour']);
        $this->assertSame('27.5', $record->insitu['W1']['temperature']);
    }

    public function test_index_filters_by_project_and_date_range(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $otherProject = $this->project();

        WaterQualityRecord::create([
            'project_id' => $project->id,
            'sample_date' => '2026-09-01',
        ]);
        WaterQualityRecord::create([
            'project_id' => $project->id,
            'sample_date' => '2026-09-15',
        ]);
        WaterQualityRecord::create([
            'project_id' => $otherProject->id,
            'sample_date' => '2026-09-10',
        ]);

        $response = $this->actingAs($actor)->getJson(
            "/api/environment/water-quality?project_id={$project->id}&from=2026-09-05&to=2026-09-20"
        );

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertSame('2026-09-15', substr($data[0]['sample_date'], 0, 10));
    }

    public function test_invalid_condition_value_returns_422(): void
    {
        $actor = $this->actor();
        $project = $this->project();

        $response = $this->actingAs($actor)->postJson('/api/environment/water-quality', [
            'project_id' => $project->id,
            'sample_date' => '2026-09-10',
            'conditions' => [
                'W1' => ['odour' => 'Not A Valid Option'],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_pdf_endpoint_returns_pdf(): void
    {
        $actor = $this->actor();
        $project = $this->project();

        $record = WaterQualityRecord::create([
            'project_id' => $project->id,
            'sample_date' => '2026-09-10',
        ]);

        $response = $this->actingAs($actor)->get("/api/environment/water-quality/{$record->id}/pdf");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_view_only_user_gets_403_on_store(): void
    {
        $actor = $this->actor(['environmental.view']);
        $project = $this->project();

        $response = $this->actingAs($actor)->postJson('/api/environment/water-quality', [
            'project_id' => $project->id,
            'sample_date' => '2026-09-10',
        ]);

        $response->assertStatus(403);
    }
}
