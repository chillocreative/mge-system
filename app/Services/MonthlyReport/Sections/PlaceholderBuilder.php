<?php

namespace App\Services\MonthlyReport\Sections;

use App\Services\MonthlyReport\ReportContext;

final class PlaceholderBuilder extends AbstractBuilder
{
    public function build(ReportContext $ctx): array
    {
        return ['schema' => 1, 'placeholder' => true];
    }
}
