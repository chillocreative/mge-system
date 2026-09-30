<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AssetUsageReportExport implements FromArray, ShouldAutoSize, WithHeadings
{
    public function __construct(private array $rows) {}

    public function array(): array
    {
        return array_map(fn (array $row) => [
            $row['asset_no'],
            $row['asset'],
            ucfirst($row['category']),
            ucfirst(str_replace('_', ' ', $row['type'])),
            $row['projects'] ?: '-',
            $row['monthly_usage_days'],
            $row['monthly_assignment_count'],
            $row['total_usage_days'],
            $row['last_maintenance_date'] ?: '-',
            $row['maintenance_due_date'] ?: '-',
            ucfirst(str_replace('_', ' ', $row['maintenance_status'])),
            $row['road_tax_expiry_date'] ?: '-',
            $row['road_tax_days_remaining'] ?? '-',
            ucfirst(str_replace('_', ' ', $row['road_tax_status'])),
        ], $this->rows);
    }

    public function headings(): array
    {
        return [
            'Asset No.', 'Asset', 'Category', 'Type', 'Projects This Month',
            'Monthly Usage (Days)', 'Assignments This Month', 'Total Usage (Days)',
            'Last Maintenance', 'Maintenance Due', 'Maintenance Status',
            'Road Tax Expiry', 'Road Tax Days Remaining', 'Road Tax Status',
        ];
    }
}
