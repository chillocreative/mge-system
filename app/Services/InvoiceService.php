<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function generateInvoiceNumber(): string
    {
        $year = now()->format('Y');
        $lastInvoice = Invoice::withTrashed()
            ->where('invoice_number', 'like', "INV-{$year}-%")
            ->orderByDesc('invoice_number')
            ->first();

        $sequence = 1;
        if ($lastInvoice) {
            $parts = explode('-', $lastInvoice->invoice_number);
            $sequence = (int) end($parts) + 1;
        }

        return sprintf('INV-%s-%04d', $year, $sequence);
    }

    public function createInvoice(array $data, int $createdBy): Invoice
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $invoice = Invoice::create([
                'invoice_number' => $this->generateInvoiceNumber(),
                'project_id' => $data['project_id'] ?? null,
                'client_id' => $data['client_id'],
                'status' => 'draft',
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'tax_rate' => $data['tax_rate'] ?? 0,
                'discount' => $data['discount'] ?? 0,
                'currency' => $data['currency'] ?? 'PKR',
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'created_by' => $createdBy,
            ]);

            if (!empty($data['items'])) {
                foreach ($data['items'] as $i => $item) {
                    $invoice->items()->create([
                        'description' => $item['description'],
                        'quantity' => $item['quantity'] ?? 1,
                        'unit' => $item['unit'] ?? 'unit',
                        'unit_price' => $item['unit_price'],
                        'sort_order' => $i,
                    ]);
                }
            }

            $invoice->recalculateTotals();

            return $invoice->fresh(['items', 'client', 'project', 'creator']);
        });
    }

    public function updateInvoice(int $id, array $data): Invoice
    {
        return DB::transaction(function () use ($id, $data) {
            $invoice = Invoice::findOrFail($id);

            $invoice->update(array_filter([
                'project_id' => $data['project_id'] ?? $invoice->project_id,
                'client_id' => $data['client_id'] ?? $invoice->client_id,
                'issue_date' => $data['issue_date'] ?? $invoice->issue_date,
                'due_date' => $data['due_date'] ?? $invoice->due_date,
                'tax_rate' => $data['tax_rate'] ?? $invoice->tax_rate,
                'discount' => $data['discount'] ?? $invoice->discount,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $invoice->notes,
                'terms' => array_key_exists('terms', $data) ? $data['terms'] : $invoice->terms,
            ], fn ($v) => $v !== null));

            if (isset($data['items'])) {
                $invoice->items()->delete();
                foreach ($data['items'] as $i => $item) {
                    $invoice->items()->create([
                        'description' => $item['description'],
                        'quantity' => $item['quantity'] ?? 1,
                        'unit' => $item['unit'] ?? 'unit',
                        'unit_price' => $item['unit_price'],
                        'sort_order' => $i,
                    ]);
                }
            }

            $invoice->recalculateTotals();

            return $invoice->fresh(['items', 'client', 'project', 'creator', 'payments']);
        });
    }

    public function listInvoices(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Invoice::with(['client:id,company_name', 'project:id,name,code', 'creator:id,first_name,last_name'])
            ->withCount('payments');

        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }
        if (!empty($filters['client_id'])) {
            $query->forClient((int) $filters['client_id']);
        }
        if (!empty($filters['project_id'])) {
            $query->forProject((int) $filters['project_id']);
        }
        if (!empty($filters['search'])) {
            $query->where('invoice_number', 'like', "%{$filters['search']}%");
        }
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->whereBetween('issue_date', [$filters['date_from'], $filters['date_to']]);
        }

        return $query->latest('issue_date')->paginate($perPage);
    }

    public function getInvoice(int $id): Invoice
    {
        return Invoice::with([
            'items',
            'payments.receiver:id,first_name,last_name',
            'client',
            'project:id,name,code',
            'creator:id,first_name,last_name',
        ])->findOrFail($id);
    }

    public function recordPayment(int $invoiceId, array $data, int $receivedBy): Payment
    {
        $invoice = Invoice::findOrFail($invoiceId);

        $payment = $invoice->payments()->create([
            'amount' => $data['amount'],
            'payment_date' => $data['payment_date'],
            'method' => $data['method'] ?? 'bank_transfer',
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'received_by' => $receivedBy,
        ]);

        $invoice->recalculateTotals();

        return $payment->load('receiver:id,first_name,last_name');
    }

    public function markAsSent(int $id): Invoice
    {
        $invoice = Invoice::findOrFail($id);
        if ($invoice->status === 'draft') {
            $invoice->update(['status' => 'sent']);
        }
        return $invoice->fresh();
    }

    public function generatePdf(int $id): \Barryvdh\DomPDF\PDF
    {
        $invoice = $this->getInvoice($id);

        return Pdf::loadView('pdf.invoice', compact('invoice'))
            ->setPaper('a4', 'portrait');
    }

    public function deleteInvoice(int $id): bool
    {
        return Invoice::findOrFail($id)->delete();
    }
}
