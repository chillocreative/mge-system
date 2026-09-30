<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProjectFinanceReportExport implements FromArray, ShouldAutoSize, WithHeadings
{
    public function __construct(private array $rows) {}

    public function array(): array
    {
        return array_map(fn ($row) => [$row['month'] ?? '', $row['budgeted_cost'] ?? 0, $row['actual_cost'] ?? 0, $row['certified_claims'] ?? 0], $this->rows);
    }

    public function headings(): array
    {
        return ['Month', 'Budgeted Cost', 'Actual Cost', 'Certified Claims'];
    }
}
