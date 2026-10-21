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
        if ($this->resource === 'expenses') {
            return $this->rows->map(fn ($r) => [$r->expense_date?->format('Y-m-d'), $r->category, $r->description, $r->quantity, $r->unit, $r->unit_price, $r->amount, $r->payment_method, $r->invoice_no, $r->do_no, $r->vendor, $r->amount_source_mismatch, $r->amount_calculated]);
        }

        if ($this->resource === 'client-claims') {
            return $this->rows->map(fn ($r) => [$r->id, $r->project?->name, $r->client?->company_name, $r->claim_no, $r->invoice_no, $r->do_no, $r->submitted_date?->format('Y-m-d'), $r->certified_date?->format('Y-m-d'), $r->paid_date?->format('Y-m-d'), $r->amount, $r->certified_amount, $r->status, $r->notes]);
        }

        return $this->rows->map(fn ($r) => $r->toArray());
    }

    public function headings(): array
    {
        return match ($this->resource) {
            'expenses' => ['Date', 'Category', 'Description', 'Qty', 'Unit', 'Price per Unit', 'Amount', 'Payment Method', 'Invoice No.', 'DO No.', 'Vendor', 'Amount Source Mismatch', 'Amount Calculated'],'budgets' => ['id', 'project_id', 'month', 'category', 'budgeted_cost', 'notes'],'vendor-payments' => ['id', 'project_id', 'vendor', 'invoice_no', 'do_no', 'invoice_date', 'submitted_date', 'due_date', 'paid_date', 'amount', 'status', 'notes'],'subcontractor-claims' => ['id', 'project_id', 'subcontractor', 'claim_no', 'invoice_no', 'do_no', 'submitted_date', 'certified_date', 'paid_date', 'amount', 'certified_amount', 'status', 'notes'],'client-claims' => ['id', 'project', 'client', 'claim_no', 'invoice_no', 'do_no', 'submitted_date', 'certified_date', 'paid_date', 'amount', 'certified_amount', 'status', 'notes'],default => []
        };
    }
}
