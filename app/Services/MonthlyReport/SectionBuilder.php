<?php

namespace App\Services\MonthlyReport;

interface SectionBuilder
{
    public function key(): string;

    public function title(): string;

    /** @return array<string, mixed> with 'schema' => 1 */
    public function build(ReportContext $ctx): array;
}
