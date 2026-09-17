<?php

namespace App\Services\MonthlyReport\Sections;

use App\Models\ProjectProgressPeriod;
use App\Models\ProjectScheduleBaseline;
use App\Services\MonthlyReport\ReportContext;

final class FinancialSCurveBuilder extends AbstractBuilder
{
    public function build(ReportContext $ctx): array
    {
        $baselines = ProjectScheduleBaseline::where('project_id', $ctx->project->id)->orderBy('month')->get();
        $periods = ProjectProgressPeriod::where('project_id', $ctx->project->id)->get();

        $months = [];
        $scheduled = [];
        $scheduledPct = [];
        $actual = [];
        $actualPct = [];

        foreach ($baselines as $baseline) {
            $months[] = $baseline->month->format('M-y');
            $scheduled[] = (float) $baseline->scheduled_financial_amount;
            $scheduledPct[] = (float) $baseline->scheduled_financial_pct;

            $match = $periods->first(fn ($p) => $p->period_end->isSameMonth($baseline->month) && $p->period_end->isSameYear($baseline->month));
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
