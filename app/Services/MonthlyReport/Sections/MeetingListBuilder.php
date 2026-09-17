<?php

namespace App\Services\MonthlyReport\Sections;

use App\Models\MeetingMinute;
use App\Services\MonthlyReport\ReportContext;

final class MeetingListBuilder extends AbstractBuilder
{
    public function build(ReportContext $ctx): array
    {
        $meetings = MeetingMinute::where('project_id', $ctx->project->id)
            ->orderBy('meeting_date')
            ->get();

        $rows = $meetings->values()->map(fn (MeetingMinute $meeting, int $index) => [
            'no' => $index + 1,
            'description' => $meeting->title,
            'date' => $meeting->meeting_date?->format('d.m.Y'),
            'location' => $meeting->location,
        ])->all();

        return ['schema' => 1, 'rows' => $rows];
    }
}
