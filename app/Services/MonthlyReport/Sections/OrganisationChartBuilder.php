<?php

namespace App\Services\MonthlyReport\Sections;

use App\Services\MonthlyReport\ReportContext;
use Illuminate\Support\Collection;

final class OrganisationChartBuilder extends AbstractBuilder
{
    public function build(ReportContext $ctx): array
    {
        $rows = $ctx->project->members()->whereNull('project_members.left_at')->get()->map(fn ($u) => [
            'user_id' => $u->id,
            'name' => trim($u->first_name.' '.$u->last_name),
            'designation' => $u->pivot->designation,
            'reports_to_user_id' => $u->pivot->reports_to_user_id,
            'org_sort' => (int) $u->pivot->org_sort,
        ])->sortBy('org_sort')->values();

        // Same "orphaned manager renders as root" rule as OrgChartController::show().
        $activeIds = $rows->pluck('user_id')->all();
        $renderRows = $rows->map(function ($r) use ($activeIds) {
            if ($r['reports_to_user_id'] !== null && ! in_array($r['reports_to_user_id'], $activeIds, true)) {
                $r['reports_to_user_id'] = null;
            }

            return $r;
        });

        $contractor = $ctx->party('contractor');

        return [
            'schema' => 1,
            'company' => $contractor?->name ?? config('payroll.company.name'),
            'tree' => $this->tree($renderRows, null, []),
            'note' => '',
        ];
    }

    private function tree(Collection $rows, ?int $parentId, array $visited): array
    {
        return $rows->where('reports_to_user_id', $parentId)
            ->reject(fn ($r) => isset($visited[$r['user_id']]))
            ->map(function ($r) use ($rows, $visited) {
                $visited[$r['user_id']] = true;

                return [
                    'name' => $r['name'],
                    'designation' => $r['designation'],
                    'children' => $this->tree($rows, $r['user_id'], $visited),
                ];
            })->values()->all();
    }
}
