<?php

namespace App\Services\MonthlyReport\Sections;

use App\Models\ProgrammeActivity;
use App\Services\MonthlyReport\ReportContext;
use App\Services\ReportData\ProgrammeService;

final class WorkProgrammeBuilder extends AbstractBuilder
{
    public function build(ReportContext $ctx): array
    {
        $version = app(ProgrammeService::class)->currentFor($ctx->project->id);

        if ($version === null) {
            return [
                'schema' => 1,
                'version' => null,
                'note' => 'No work programme has been imported for this project yet (Report Data › Work Programme).',
                'rows' => [],
            ];
        }

        $rows = $version->activities()->orderBy('seq')
            ->get()
            ->map(fn (ProgrammeActivity $activity) => [
                'no' => $activity->seq,
                'task' => $activity->name,
                'level' => $activity->outline_level,
                'summary' => (bool) $activity->is_summary,
                'duration' => $this->formatDuration($activity->duration_days),
                'start' => $activity->start?->format('d/m/Y') ?? '',
                'finish' => $activity->finish?->format('d/m/Y') ?? '',
                'actual' => $this->formatPercent($activity->actual_pct),
                'plan' => $this->formatPercent($activity->plan_pct),
            ])
            ->all();

        return [
            'schema' => 1,
            'version' => [
                'id' => $version->id,
                'label' => $version->label,
                'status_date' => $version->status_date?->format('d/m/Y') ?? '',
            ],
            'note' => null,
            'rows' => $rows,
        ];
    }

    private function formatDuration(?int $days): string
    {
        if ($days === null) {
            return '';
        }

        return $days === 1 ? '1 day' : "{$days} days";
    }

    private function formatPercent(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $formatted = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

        return ($formatted === '' ? '0' : $formatted).'%';
    }
}
