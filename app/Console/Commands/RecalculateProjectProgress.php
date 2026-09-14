<?php

namespace App\Console\Commands;

use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill `projects.progress` for projects created before the
 * MilestoneObserver started rolling milestone progress up automatically.
 *
 * `projects.progress` used to be a raw, independently-stored column that was
 * never calculated from milestones — it only changed if someone PATCHed it
 * directly, which the UI never does. The observer now keeps it in sync going
 * forward for every milestone create/update/delete, but existing projects
 * with milestones need a one-time backfill after deploying. Dry-run by
 * default; pass --commit to actually write.
 */
class RecalculateProjectProgress extends Command
{
    protected $signature = 'projects:recalculate-progress
        {--commit : Write the recalculated progress values. Without this, nothing is saved}';

    protected $description = 'Recalculate each project\'s overall progress as the average of its milestones\' progress (dry run by default)';

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');

        $projects = Project::has('milestones')->get();

        if ($projects->isEmpty()) {
            $this->info('No projects with milestones found. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s progress for %d project(s) with milestones.',
            $commit ? 'Recalculating and WRITING' : 'Recalculating in DRY RUN (nothing will be written):',
            $projects->count(),
        ));
        $this->newLine();

        $changes = [];

        foreach ($projects as $project) {
            $average = (int) round($project->milestones()->avg('progress') ?? 0);

            if ($average !== (int) $project->progress) {
                $changes[] = [
                    $project->id,
                    $project->code ?? $project->name,
                    $project->progress,
                    $average,
                ];
            }
        }

        if ($changes === []) {
            $this->info('No differences. Every project\'s stored progress already matches its milestone average.');

            return self::SUCCESS;
        }

        $this->table(
            ['Project ID', 'Project Name/Code', 'Current Progress', 'New Progress'],
            $changes,
        );

        if ($commit) {
            DB::transaction(function () use ($projects) {
                foreach ($projects as $project) {
                    $average = (int) round($project->milestones()->avg('progress') ?? 0);

                    if ($average !== (int) $project->progress) {
                        $project->update(['progress' => $average]);
                    }
                }
            });

            $this->newLine();
            $this->info(count($changes).' project(s) updated.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->comment('Dry run — pass --commit to apply changes.');

        return self::SUCCESS;
    }
}
