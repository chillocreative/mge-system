<?php

namespace App\Services\MonthlyReport\Sections;

use App\Models\ProjectDelayNotice;
use App\Services\MonthlyReport\ReportContext;

final class DelayNoticeBuilder extends AbstractBuilder
{
    public function build(ReportContext $ctx): array
    {
        $notices = ProjectDelayNotice::where('project_id', $ctx->project->id)
            ->orderBy('sort_order')
            ->orderBy('submitted_date')
            ->get();

        $rows = $notices->values()->map(function (ProjectDelayNotice $notice, int $index) {
            $submitted = $notice->submitted_date?->format('d.m.Y');
            if ($submitted && $notice->submitted_via) {
                $submitted .= "\nvia {$notice->submitted_via}";
            }

            return [
                'no' => $index + 1,
                'title' => $notice->title,
                'issue' => $notice->issue,
                'reg_number' => $notice->reg_number,
                'submitted' => $submitted,
                'reply' => $notice->reply_date?->format('d.m.Y') ?? '-',
                'duration' => $notice->duration_days,
                'status' => $notice->status,
                'impact' => $notice->impact,
            ];
        })->all();

        return ['schema' => 1, 'rows' => $rows];
    }
}
