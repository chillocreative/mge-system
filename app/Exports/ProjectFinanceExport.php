<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProjectFinanceExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public function __construct(private string $resource, private Collection $rows) {}

    public function collection()
    {
        return $this->rows->map(fn ($r) => $r->toArray());
    }

    public function headings(): array
    {
        return match ($this->resource) {
            'expenses' => ['id', 'project_id', 'expense_date', 'category', 'description', 'vendor', 'invoice_no', 'do_no', 'amount', 'status', 'notes'],'budgets' => ['id', 'project_id', 'month', 'category', 'budgeted_cost', 'notes'],'vendor-payments' => ['id', 'project_id', 'vendor', 'invoice_no', 'do_no', 'invoice_date', 'submitted_date', 'due_date', 'paid_date', 'amount', 'status', 'notes'],'subcontractor-claims' => ['id', 'project_id', 'subcontractor', 'claim_no', 'invoice_no', 'do_no', 'submitted_date', 'certified_date', 'paid_date', 'amount', 'certified_amount', 'status', 'notes'],default => []
        };
    }
}
