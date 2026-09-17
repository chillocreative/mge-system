<?php

namespace App\Services\MonthlyReport\Sections;

use App\Models\ProjectScheduleBaseline;
use App\Services\MonthlyReport\ReportContext;

final class PhysicalSCurveBuilder extends AbstractBuilder
{
    public function build(ReportContext $ctx): array
    {
        $baselines = ProjectScheduleBaseline::where('project_id', $ctx->project->id)->orderBy('month')->get();
        $periodsByMonth = $this->periodsByMonth($ctx);

        $months = [];
        $scheduled = [];
        $actual = [];

        foreach ($baselines as $baseline) {
            $months[] = $baseline->month->format('M-y');
            $scheduled[] = (float) $baseline->scheduled_physical_pct;

            $match = $periodsByMonth[$baseline->month->format('Y-m')] ?? null;
            $actual[] = $match ? (float) $match->physical_actual_pct : null;
        }

        return [
            'schema' => 1,
            'series' => ['months' => $months, 'scheduled' => $scheduled, 'actual' => $actual],
            'chart_asset_id' => null,
        ];
    }
}
