<?php

namespace App\Services\MonthlyReport\Sections;

use App\Models\ProjectProgressPeriod;
use App\Models\ProjectScheduleBaseline;
use App\Services\MonthlyReport\ReportContext;

final class PhysicalSCurveBuilder extends AbstractBuilder
{
    public function build(ReportContext $ctx): array
    {
        $baselines = ProjectScheduleBaseline::where('project_id', $ctx->project->id)->orderBy('month')->get();
        $periods = ProjectProgressPeriod::where('project_id', $ctx->project->id)->get();

        $months = [];
        $scheduled = [];
        $actual = [];

        foreach ($baselines as $baseline) {
            $months[] = $baseline->month->format('M-y');
            $scheduled[] = (float) $baseline->scheduled_physical_pct;

            $match = $periods->first(fn ($p) => $p->period_end->isSameMonth($baseline->month) && $p->period_end->isSameYear($baseline->month));
            $actual[] = $match ? (float) $match->physical_actual_pct : null;
        }

        return [
            'schema' => 1,
            'series' => ['months' => $months, 'scheduled' => $scheduled, 'actual' => $actual],
            'chart_asset_id' => null,
        ];
    }
}
