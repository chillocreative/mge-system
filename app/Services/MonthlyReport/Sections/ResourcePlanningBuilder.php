<?php

namespace App\Services\MonthlyReport\Sections;

use App\Services\MonthlyReport\ReportContext;

final class ResourcePlanningBuilder extends AbstractBuilder
{
    public function build(ReportContext $ctx): array
    {
        $members = $ctx->project->members()->whereNull('project_members.left_at')->get();

        $rows = $members
            ->groupBy(fn ($u) => $u->pivot->designation ?: 'Unassigned')
            ->map(fn ($group, $designation) => [
                'designation' => $designation,
                'nos' => $group->count(),
                'sort' => $group->min(fn ($u) => (int) $u->pivot->org_sort),
            ])
            ->sortBy('sort')
            ->values()
            ->map(fn ($r) => ['designation' => $r['designation'], 'nos' => $r['nos']])
            ->all();

        $contractor = $ctx->party('contractor');

        return [
            'schema' => 1,
            'company' => $contractor?->name ?? config('payroll.company.name'),
            'rows' => $rows,
        ];
    }
}
