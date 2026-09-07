<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\SiteLog;
use App\Models\SiteLogMachinery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ciri 2 — machinery usage counted in DISTINCT DAYS (plan 2.3.1), so a machine
 * logged several times on one day is one day, not several.
 */
class MachineryReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('projects.view', 'web');
    }

    private function actor(): User
    {
        $u = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->givePermissionTo('projects.view');

        return $u;
    }

    private function log(Project $p, string $date): SiteLog
    {
        return SiteLog::create(['project_id' => $p->id, 'log_date' => $date, 'work_performed' => 'x', 'logged_by' => $this->actor()->id]);
    }

    public function test_a_machine_logged_many_times_in_one_day_counts_as_one_day(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);
        $day = $this->log($project, '2026-09-03');
        // Excavator recorded 7 times on the SAME day (the plan's exact case).
        for ($i = 0; $i < 7; $i++) {
            SiteLogMachinery::create(['site_log_id' => $day->id, 'machinery_type' => 'Excavator', 'quantity' => 1]);
        }
        // ...and once more on a different day.
        $day2 = $this->log($project, '2026-09-10');
        SiteLogMachinery::create(['site_log_id' => $day2->id, 'machinery_type' => 'Excavator', 'quantity' => 1]);

        $res = $this->actingAs($this->actor())
            ->getJson("/api/projects/{$project->id}/site-logs/report/machinery?month=2026-09")
            ->assertOk();

        $excavator = collect($res->json('data.machinery'))->firstWhere('type', 'Excavator');
        $this->assertSame(2, $excavator['days_used'], 'Seven logs on one day + one on another = 2 days, not 8.');
        $this->assertSame(2, $res->json('data.working_days'));
        $this->assertSame(100, $excavator['utilisation']);
    }

    public function test_utilisation_is_days_used_over_working_days(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);
        foreach (['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04'] as $d) {
            $this->log($project, $d);
        }
        // Roller used on only one of the four logged days.
        $log = SiteLog::where('project_id', $project->id)->where('log_date', '2026-09-01')->first();
        SiteLogMachinery::create(['site_log_id' => $log->id, 'machinery_type' => 'Roller', 'quantity' => 1]);

        $res = $this->actingAs($this->actor())
            ->getJson("/api/projects/{$project->id}/site-logs/report/machinery?month=2026-09")
            ->assertOk();

        $roller = collect($res->json('data.machinery'))->firstWhere('type', 'Roller');
        $this->assertSame(1, $roller['days_used']);
        $this->assertSame(4, $res->json('data.working_days'));
        $this->assertSame(25, $roller['utilisation']);
    }

    public function test_it_only_counts_the_requested_month(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);
        $aug = $this->log($project, '2026-08-31');
        SiteLogMachinery::create(['site_log_id' => $aug->id, 'machinery_type' => 'Crane', 'quantity' => 1]);
        $sep = $this->log($project, '2026-09-01');
        SiteLogMachinery::create(['site_log_id' => $sep->id, 'machinery_type' => 'Crane', 'quantity' => 1]);

        $res = $this->actingAs($this->actor())
            ->getJson("/api/projects/{$project->id}/site-logs/report/machinery?month=2026-09")
            ->assertOk();

        $this->assertSame(1, collect($res->json('data.machinery'))->firstWhere('type', 'Crane')['days_used']);
    }
}
