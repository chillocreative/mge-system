<?php

namespace App\Services\MonthlyReport\Sections;

use App\Services\MonthlyReport\ReportContext;

final class WorkProgrammeBuilder extends AbstractBuilder
{
    public function build(ReportContext $ctx): array
    {
        return [
            'schema' => 1,
            'note' => 'Work programme import is available in a later phase.',
            'rows' => [],
        ];
    }
}
