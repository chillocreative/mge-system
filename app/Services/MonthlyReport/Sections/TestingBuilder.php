<?php

namespace App\Services\MonthlyReport\Sections;

use App\Models\ProjectTest;
use App\Services\MonthlyReport\ReportContext;

final class TestingBuilder extends AbstractBuilder
{
    public function build(ReportContext $ctx): array
    {
        $tests = ProjectTest::where('project_id', $ctx->project->id)
            ->orderBy('sort_order')
            ->orderBy('test_date')
            ->get();

        $rows = $tests->values()->map(fn (ProjectTest $test, int $index) => [
            'no' => $index + 1,
            'ref_no' => $test->ref_no,
            'name' => $test->name,
            'date' => $test->test_date?->format('d/m/Y'),
            'result' => $test->result,
            'remarks' => $test->remarks,
        ])->all();

        return ['schema' => 1, 'rows' => $rows];
    }
}
