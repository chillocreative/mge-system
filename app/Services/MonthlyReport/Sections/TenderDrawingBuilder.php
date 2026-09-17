<?php

namespace App\Services\MonthlyReport\Sections;

use App\Models\Drawing;
use App\Services\MonthlyReport\ReportContext;

final class TenderDrawingBuilder extends AbstractBuilder
{
    public function build(ReportContext $ctx): array
    {
        $drawings = Drawing::where('project_id', $ctx->project->id)
            ->where('is_tender', true)
            ->orderBy('drawing_no')
            ->get();

        $rows = $drawings->values()->map(fn (Drawing $drawing, int $index) => [
            'no' => $index + 1,
            'drawing_no' => $drawing->drawing_no,
            'title' => $drawing->title,
        ])->all();

        return ['schema' => 1, 'rows' => $rows];
    }
}
