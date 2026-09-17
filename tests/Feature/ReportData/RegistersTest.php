<?php

namespace Tests\Feature\ReportData;

use App\Models\Project;
use App\Models\ProjectDelayNotice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RegistersTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->editor = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $this->editor->givePermissionTo(['projects.view', 'projects.edit']);
    }

    private function project(): Project
    {
        return Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
    }

    public function test_delay_notice_duration_is_reply_minus_submitted(): void
    {
        $project = $this->project();
        $res = $this->actingAs($this->editor)->postJson("/api/projects/{$project->id}/delay-notices", [
            'title' => 'Setting-Out Plan', 'issue' => 'Delay Setting-Out Plan', 'reg_number' => 'MGE/JPS-TGOLAK/ADM/25-008',
            'submitted_date' => '2025-11-19', 'submitted_via' => 'email', 'reply_date' => '2025-12-08', 'status' => 'close',
            'impact' => 'Prevents key site activities.',
        ])->assertCreated();

        $this->assertSame(19, $res->json('data.duration_days'));
    }

    public function test_open_notice_duration_counts_to_today_and_crud_is_project_scoped(): void
    {
        $p1 = $this->project();
        $p2 = $this->project();
        $n = ProjectDelayNotice::create(['project_id' => $p1->id, 'title' => 'X', 'issue' => 'Y', 'reg_number' => 'R', 'submitted_date' => now()->subDays(5)->toDateString(), 'status' => 'open']);

        $this->assertSame(5, $n->fresh()->duration_days);
        $this->actingAs($this->editor)->deleteJson("/api/projects/{$p2->id}/delay-notices/{$n->id}")->assertNotFound();
        $this->actingAs($this->editor)->deleteJson("/api/projects/{$p1->id}/delay-notices/{$n->id}")->assertOk();
    }

    public function test_tests_register_crud(): void
    {
        $project = $this->project();
        $res = $this->actingAs($this->editor)->postJson("/api/projects/{$project->id}/tests", [
            'ref_no' => 'T-001', 'name' => 'Trial mix (jet grouting)', 'test_date' => '2026-01-09', 'result' => 'Pass',
        ])->assertCreated();
        $id = $res->json('data.id');

        $this->actingAs($this->editor)->putJson("/api/projects/{$project->id}/tests/{$id}", ['result' => 'Fail', 'remarks' => 'Retest'])->assertOk();
        $list = $this->actingAs($this->editor)->getJson("/api/projects/{$project->id}/tests")->assertOk();
        $this->assertSame('Fail', $list->json('data.0.result'));
    }
}
