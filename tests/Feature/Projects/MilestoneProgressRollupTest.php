<?php

namespace Tests\Feature\Projects;

use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A Project's "Overall completion" (`projects.progress`) used to be a raw,
 * independently-stored column never derived from anything — it only changed
 * if someone PATCHed it directly, which the UI never does, so it always
 * showed 0% even for projects with milestones in progress.
 *
 * `MilestoneObserver` now keeps `projects.progress` in sync as the average of
 * its milestones' `progress` values, on every create/update/delete of a
 * milestone, regardless of which controller entry point triggers it.
 */
class MilestoneProgressRollupTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_milestones_updates_project_progress_to_the_average(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'PR'.random_int(1000, 9999), 'status' => 'in_progress']);

        Milestone::create(['project_id' => $project->id, 'title' => 'M1', 'progress' => 100]);
        Milestone::create(['project_id' => $project->id, 'title' => 'M2', 'progress' => 0]);

        $project->refresh();

        $this->assertSame(50, (int) $project->progress);
    }

    public function test_updating_a_milestones_progress_recalculates_project_progress(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'PR'.random_int(1000, 9999), 'status' => 'in_progress']);

        $m1 = Milestone::create(['project_id' => $project->id, 'title' => 'M1', 'progress' => 0]);
        Milestone::create(['project_id' => $project->id, 'title' => 'M2', 'progress' => 0]);

        $project->refresh();
        $this->assertSame(0, (int) $project->progress);

        $m1->update(['progress' => 100]);

        $project->refresh();
        $this->assertSame(50, (int) $project->progress);
    }

    public function test_deleting_a_milestone_recalculates_project_progress_excluding_it(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'PR'.random_int(1000, 9999), 'status' => 'in_progress']);

        $m1 = Milestone::create(['project_id' => $project->id, 'title' => 'M1', 'progress' => 100]);
        $m2 = Milestone::create(['project_id' => $project->id, 'title' => 'M2', 'progress' => 0]);

        $project->refresh();
        $this->assertSame(50, (int) $project->progress);

        $m2->delete();

        $project->refresh();
        $this->assertSame(100, (int) $project->progress);
    }

    public function test_project_with_no_milestones_keeps_progress_at_zero(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'PR'.random_int(1000, 9999), 'status' => 'in_progress']);

        $project->refresh();

        $this->assertSame(0, (int) $project->progress);
    }
}
