<?php

namespace App\Observers;

use App\Models\Milestone;

class MilestoneObserver
{
    public function saved(Milestone $milestone): void
    {
        $this->recalculateProjectProgress($milestone);
    }

    public function deleted(Milestone $milestone): void
    {
        $this->recalculateProjectProgress($milestone);
    }

    /**
     * Recalculate the parent project's `progress` as the average of its
     * milestones' `progress` values. Always recomputed against a fresh query
     * on the relation (rather than a stale in-memory collection), so that the
     * `deleted` event — which fires after the row is already removed from the
     * database — naturally excludes the deleted milestone.
     */
    protected function recalculateProjectProgress(Milestone $milestone): void
    {
        $project = $milestone->project;

        if (! $project) {
            return;
        }

        $average = (int) round($project->milestones()->avg('progress') ?? 0);

        if ($project->progress !== $average) {
            $project->update(['progress' => $average]);
        }
    }
}
