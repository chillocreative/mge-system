<?php

namespace App\Services\MonthlyReport\Sections;

use App\Services\MonthlyReport\ReportContext;
use App\Services\MonthlyReport\Sections\Concerns\SummarisesSiteLogsByDay;

final class TradeWorkerBuilder extends AbstractBuilder
{
    use SummarisesSiteLogsByDay;

    public function build(ReportContext $ctx): array
    {
        $days = $this->dayAxis($ctx->period->period_start, $ctx->period->period_end);
        $data = $this->daySums($ctx, 'workers', 'worker_type', 'count');

        $rows = $this->categoryRows($ctx->project->id, 'worker', 'Tradesman');
        $names = array_column($rows, 'name');

        foreach ($data['loggedTypes'] as $type) {
            if (! in_array($type, $names, true)) {
                $rows[] = ['name' => $type, 'group' => 'Tradesman'];
                $names[] = $type;
            }
        }

        $groupOrder = [];
        $rowsByGroup = [];
        $no = 1;

        foreach ($rows as $row) {
            $group = $row['group'] ?? 'Tradesman';

            if (! isset($rowsByGroup[$group])) {
                $rowsByGroup[$group] = [];
                $groupOrder[] = $group;
            }

            $counts = [];
            foreach ($days as $i => $day) {
                $counts[] = $data['hasLog'][$i] ? ($data['sums'][$row['name']][$i] ?? 0) : null;
            }

            $rowsByGroup[$group][] = ['no' => $no++, 'description' => $row['name'], 'counts' => $counts];
        }

        $groups = array_map(fn (string $label) => ['label' => $label, 'rows' => $rowsByGroup[$label]], $groupOrder);

        $totals = [];
        foreach ($days as $i => $day) {
            if (! $data['hasLog'][$i]) {
                $totals[] = null;

                continue;
            }

            $sum = 0;
            foreach ($rows as $row) {
                $sum += $data['sums'][$row['name']][$i] ?? 0;
            }
            $totals[] = $sum;
        }

        return [
            'schema' => 1,
            'days' => $days,
            'groups' => $groups,
            'totals' => $totals,
        ];
    }
}
