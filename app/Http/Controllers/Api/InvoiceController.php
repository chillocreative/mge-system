<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'client_id', 'project_id', 'search', 'date_from', 'date_to']);
        $invoices = $this->invoiceService->listInvoices($filters, $request->integer('per_page', 15));

        return $this->success($invoices);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $invoice = $this->invoiceService->createInvoice($validated, $request->user()->id);

        return $this->created($invoice, 'Invoice created.');
    }

    public function show(int $id): JsonResponse
    {
        $invoice = $this->invoiceService->getInvoice($id);

        return $this->success($invoice);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['sometimes', 'exists:clients,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'issue_date' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'date'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.description' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
        ]);

        $invoice = $this->invoiceService->updateInvoice($id, $validated);

        return $this->success($invoice, 'Invoice updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->invoiceService->deleteInvoice($id);

        return $this->success(null, 'Invoice deleted.');
    }

    public function recordPayment(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'method' => ['nullable', 'in:bank_transfer,cheque,cash,online,other'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = $this->invoiceService->recordPayment($id, $validated, $request->user()->id);

        return $this->created($payment, 'Payment recorded.');
    }

    public function markAsSent(int $id): JsonResponse
    {
        $invoice = $this->invoiceService->markAsSent($id);

        return $this->success($invoice, 'Invoice marked as sent.');
    }

    public function downloadPdf(int $id)
    {
        $pdf = $this->invoiceService->generatePdf($id);
        $invoice = $this->invoiceService->getInvoice($id);

        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }

    public function previewPdf(int $id)
    {
        $pdf = $this->invoiceService->generatePdf($id);

        return $pdf->stream();
    }
}
