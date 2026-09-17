<?php

namespace App\Services\MonthlyReport\Sections;

use App\Models\CorrespondenceType;
use App\Models\ProjectCorrespondence;
use App\Services\MonthlyReport\ReportContext;
use Illuminate\Support\Carbon;

final class DocumentSubmissionBuilder extends AbstractBuilder
{
    public function build(ReportContext $ctx): array
    {
        $types = CorrespondenceType::whereNotNull('report_group')->orderBy('sort_order')->get();

        $groups = $types->map(function (CorrespondenceType $type) use ($ctx) {
            $base = ProjectCorrespondence::where('project_id', $ctx->project->id)->where('type', $type->code);

            return [
                'code' => $type->code,
                'label' => $type->full_name,
                'accumulative' => $this->counts((clone $base), null, $ctx->period->period_end),
                'previous' => $ctx->previousPeriod
                    ? $this->counts((clone $base), null, $ctx->previousPeriod->period_end)
                    : ['issued' => 0, 'closed' => 0, 'open' => 0],
                'current' => $this->counts((clone $base), $ctx->period->period_start, $ctx->period->period_end),
            ];
        })->values()->all();

        return [
            'schema' => 1,
            'groups' => $groups,
            'previous_label' => $ctx->previousPeriod ? $ctx->previousPeriod->period_end->format('d/m/Y') : '-',
            'current_label' => $ctx->period->period_end->format('d/m/Y'),
        ];
    }

    /** @return array{issued:int,closed:int,open:int} */
    private function counts($query, ?Carbon $from, Carbon $to): array
    {
        if ($from) {
            $query->whereBetween('raised_date', [$from->format('Y-m-d'), $to->format('Y-m-d')]);
        } else {
            $query->where('raised_date', '<=', $to->format('Y-m-d'));
        }

        $issued = (clone $query)->count();
        $closed = (clone $query)->where('status', 'closed')->count();

        return ['issued' => $issued, 'closed' => $closed, 'open' => $issued - $closed];
    }
}
