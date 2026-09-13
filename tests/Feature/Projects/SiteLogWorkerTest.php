<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\SiteLogWorker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Site Log "Workers on site" can be broken down by trade (General
 * Worker, Operator, Bar Bender, Carpenter, etc.) instead of a single
 * plain number. workers_count is always server-computed as the sum of
 * the breakdown whenever one is submitted — never trusted from the
 * client directly.
 */
class SiteLogWorkerTest extends TestCase
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
        $u = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->givePermissionTo(['projects.view', 'projects.edit']);

        return $u;
    }

    public function test_a_site_log_can_record_a_worker_breakdown_by_trade(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);

        $res = $this->actingAs($this->actor())
            ->postJson("/api/projects/{$project->id}/site-logs", [
                'log_date' => '2026-09-05',
                'work_performed' => 'Formwork',
                'workers' => [
                    ['worker_type' => 'General Worker', 'count' => 5],
                    ['worker_type' => 'Carpenter', 'count' => 3],
                ],
            ])
            ->assertCreated();

        $this->assertCount(2, $res->json('data.workers'));
        $this->assertSame(8, $res->json('data.workers_count'));

        $rows = SiteLogWorker::all();
        $this->assertCount(2, $rows);
        $this->assertSame('General Worker', $rows->firstWhere('count', 5)->worker_type);
    }

    public function test_workers_count_is_always_the_server_computed_sum_not_a_client_supplied_value(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);

        $res = $this->actingAs($this->actor())
            ->postJson("/api/projects/{$project->id}/site-logs", [
                'log_date' => '2026-09-05',
                'work_performed' => 'x',
                'workers_count' => 999,
                'workers' => [
                    ['worker_type' => 'Operator', 'count' => 2],
                ],
            ])
            ->assertCreated();

        $this->assertSame(2, $res->json('data.workers_count'));
    }

    public function test_an_unknown_worker_type_is_rejected(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);

        $this->actingAs($this->actor())
            ->postJson("/api/projects/{$project->id}/site-logs", [
                'log_date' => '2026-09-05',
                'work_performed' => 'x',
                'workers' => [['worker_type' => 'Astronaut', 'count' => 1]],
            ])
            ->assertStatus(422);
    }

    public function test_updating_the_worker_breakdown_replaces_it_and_recomputes_the_total(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);
        $user = $this->actor();

        $logId = $this->actingAs($user)->postJson("/api/projects/{$project->id}/site-logs", [
            'log_date' => now()->toDateString(),
            'work_performed' => 'x',
            'workers' => [['worker_type' => 'General Worker', 'count' => 4]],
        ])->json('data.id');

        $res = $this->actingAs($user)
            ->putJson("/api/projects/{$project->id}/site-logs/{$logId}", [
                'workers' => [
                    ['worker_type' => 'Mason', 'count' => 2],
                    ['worker_type' => 'Electrician', 'count' => 1],
                ],
            ])
            ->assertOk();

        $this->assertCount(2, $res->json('data.workers'));
        $this->assertSame(3, $res->json('data.workers_count'));
        $this->assertCount(2, SiteLogWorker::where('site_log_id', $logId)->get());
    }

    public function test_a_site_log_without_a_worker_breakdown_still_works_as_before(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);

        $res = $this->actingAs($this->actor())
            ->postJson("/api/projects/{$project->id}/site-logs", [
                'log_date' => '2026-09-05',
                'work_performed' => 'x',
            ])
            ->assertCreated();

        $this->assertSame([], $res->json('data.workers'));
    }
}
