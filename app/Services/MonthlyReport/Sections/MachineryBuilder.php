<?php

namespace App\Services\MonthlyReport\Sections;

use App\Services\MonthlyReport\ReportContext;
use App\Services\MonthlyReport\Sections\Concerns\SummarisesSiteLogsByDay;

final class MachineryBuilder extends AbstractBuilder
{
    use SummarisesSiteLogsByDay;

    public function build(ReportContext $ctx): array
    {
        $days = $this->dayAxis($ctx->period->period_start, $ctx->period->period_end);
        $data = $this->daySums($ctx, 'machinery', 'machinery_type', 'quantity');

        $names = array_column($this->categoryRows($ctx->project->id, 'machinery', null), 'name');

        foreach ($data['loggedTypes'] as $type) {
            if (! in_array($type, $names, true)) {
                $names[] = $type;
            }
        }

        $rows = [];
        $no = 1;
        foreach ($names as $name) {
            $counts = [];
            foreach ($days as $i => $day) {
                $counts[] = $data['hasLog'][$i] ? ($data['sums'][$name][$i] ?? 0) : null;
            }
            $rows[] = ['no' => $no++, 'description' => $name, 'counts' => $counts];
        }

        $totals = [];
        foreach ($days as $i => $day) {
            if (! $data['hasLog'][$i]) {
                $totals[] = null;

                continue;
            }

            $sum = 0;
            foreach ($names as $name) {
                $sum += $data['sums'][$name][$i] ?? 0;
            }
            $totals[] = $sum;
        }

        return [
            'schema' => 1,
            'days' => $days,
            'rows' => $rows,
            'totals' => $totals,
        ];
    }
}
