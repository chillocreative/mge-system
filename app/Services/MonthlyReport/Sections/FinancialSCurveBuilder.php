<?php

namespace App\Services\MonthlyReport\Sections;

use App\Models\ProjectScheduleBaseline;
use App\Services\MonthlyReport\ReportContext;

final class FinancialSCurveBuilder extends AbstractBuilder
{
    public function build(ReportContext $ctx): array
    {
        $baselines = ProjectScheduleBaseline::where('project_id', $ctx->project->id)->orderBy('month')->get();
        $periodsByMonth = $this->periodsByMonth($ctx);

        $months = [];
        $scheduled = [];
        $scheduledPct = [];
        $actual = [];
        $actualPct = [];

        foreach ($baselines as $baseline) {
            $months[] = $baseline->month->format('M-y');
            $scheduled[] = (float) $baseline->scheduled_financial_amount;
            $scheduledPct[] = (float) $baseline->scheduled_financial_pct;

            $match = $periodsByMonth[$baseline->month->format('Y-m')] ?? null;
            $actual[] = $match ? (float) $match->financial_actual_amount : null;
            $actualPct[] = $match ? (float) $match->financial_actual_pct : null;
        }

        return [
            'schema' => 1,
            'series' => [
                'months' => $months,
                'scheduled' => $scheduled,
                'scheduled_pct' => $scheduledPct,
                'actual' => $actual,
                'actual_pct' => $actualPct,
            ],
            'chart_asset_id' => null,
        ];
    }
}
