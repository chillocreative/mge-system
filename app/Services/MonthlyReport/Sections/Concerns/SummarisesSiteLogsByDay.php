<?php

namespace App\Services\MonthlyReport\Sections\Concerns;

use App\Models\ProjectResourceCategory;
use App\Models\SiteLog;
use App\Services\MonthlyReport\ReportContext;
use App\Services\ReportData\ResourceCategoryService;
use Illuminate\Support\Carbon;

trait SummarisesSiteLogsByDay
{
    /** @return array<int, array{date:string, label:string, month:string, weekend:bool}> */
    protected function dayAxis(Carbon $start, Carbon $end): array
    {
        $days = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $days[] = [
                'date' => $date->format('Y-m-d'),
                'label' => $date->format('j'),
                'month' => $date->format('M-y'),
                'weekend' => $date->isWeekend(),
            ];
        }

        return $days;
    }

    /** @return array<int, array{name:string, group:?string}> */
    protected function categoryRows(int $projectId, string $kind, ?string $defaultGroup): array
    {
        $rows = ProjectResourceCategory::where('project_id', $projectId)->where('kind', $kind)->where('active', true)
            ->orderBy('sort_order')->get(['name', 'group']);

        if ($rows->isEmpty()) {
            $defaults = $kind === 'worker' ? ResourceCategoryService::DEFAULT_WORKERS : ResourceCategoryService::DEFAULT_MACHINERY;

            return array_map(fn (string $name) => ['name' => $name, 'group' => $defaultGroup], $defaults);
        }

        return $rows->map(fn (ProjectResourceCategory $row) => ['name' => $row->name, 'group' => $row->group ?? $defaultGroup])->all();
    }

    /**
     * @return array{dates: array<int,string>, hasLog: array<int,bool>, sums: array<string, array<int,int>>, loggedTypes: array<int,string>}
     */
    protected function daySums(ReportContext $ctx, string $relation, string $typeField, string $countField): array
    {
        $start = $ctx->period->period_start;
        $end = $ctx->period->period_end;

        $logsByDate = SiteLog::forProject($ctx->project->id)
            ->forPeriod($start->format('Y-m-d'), $end->format('Y-m-d'))
            ->with($relation)
            ->get()
            ->groupBy(fn (SiteLog $log) => $log->log_date->format('Y-m-d'));

        $dates = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates[] = $date->format('Y-m-d');
        }

        $sums = [];
        $loggedTypes = [];

        foreach ($dates as $i => $date) {
            $dayLogs = $logsByDate->get($date);

            if (! $dayLogs) {
                continue;
            }

            foreach ($dayLogs as $log) {
                foreach ($log->{$relation} as $item) {
                    $type = $item->{$typeField};

                    if (! in_array($type, $loggedTypes, true)) {
                        $loggedTypes[] = $type;
                    }

                    $sums[$type][$i] = ($sums[$type][$i] ?? 0) + (int) $item->{$countField};
                }
            }
        }

        return [
            'dates' => $dates,
            'hasLog' => array_map(fn (string $date) => $logsByDate->has($date), $dates),
            'sums' => $sums,
            'loggedTypes' => $loggedTypes,
        ];
    }
}
