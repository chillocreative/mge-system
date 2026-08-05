<?php

namespace App\Services;

use App\Models\ProjectInvoice;
use App\Models\ProjectInvoiceFile;
use App\Models\ProjectInvoicePayment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProjectInvoiceService
{
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = ProjectInvoice::with([
            'project:id,name,code',
            'creator:id,first_name,last_name',
            'files',
            'payments',
        ])->orderByDesc('invoice_date');

        if (!empty($filters['project_id'])) $query->forProject($filters['project_id']);
        if (!empty($filters['type'])) $query->byType($filters['type']);
        if (!empty($filters['status'])) $query->byStatus($filters['status']);
        if (!empty($filters['search'])) {
            $query->where(fn ($q) => $q->where('invoice_no', 'like', "%{$filters['search']}%")
                ->orWhere('party_name', 'like', "%{$filters['search']}%")
                ->orWhere('notes', 'like', "%{$filters['search']}%"));
        }

        return $query->paginate($perPage);
    }

    /**
     * Profit summary: total invoiced MGE→Client vs total Subcon→MGE.
     */
    public function summary(array $filters): array
    {
        $base = ProjectInvoice::query();
        if (!empty($filters['project_id'])) $base->forProject($filters['project_id']);

        $client = (float) (clone $base)->byType('client')->sum('amount');
        $subcon = (float) (clone $base)->byType('subcon')->sum('amount');

        return [
            'total_client' => round($client, 2),   // revenue: MGE invoices Client
            'total_subcon' => round($subcon, 2),    // cost: Subcon invoices MGE
            'profit' => round($client - $subcon, 2),
            'client_count' => (clone $base)->byType('client')->count(),
            'subcon_count' => (clone $base)->byType('subcon')->count(),
        ];
    }

    /**
     * Per-project profit breakdown (one row per project that has invoices).
     */
    public function summaryByProject(): array
    {
        return ProjectInvoice::query()
            ->selectRaw("project_id,
                SUM(CASE WHEN type = 'client' THEN amount ELSE 0 END) as total_client,
                SUM(CASE WHEN type = 'subcon' THEN amount ELSE 0 END) as total_subcon,
                COUNT(*) as invoice_count")
            ->groupBy('project_id')
            ->with('project:id,name,code')
            ->get()
            ->map(fn ($r) => [
                'project_id' => $r->project_id,
                'project_name' => $r->project?->name ?? 'Unknown project',
                'project_code' => $r->project?->code,
                'total_client' => round((float) $r->total_client, 2),
                'total_subcon' => round((float) $r->total_subcon, 2),
                'profit' => round((float) $r->total_client - (float) $r->total_subcon, 2),
                'invoice_count' => (int) $r->invoice_count,
            ])
            ->sortByDesc('profit')
            ->values()
            ->all();
    }

    public function create(array $data, int $userId, array $files = []): ProjectInvoice
    {
        return DB::transaction(function () use ($data, $userId, $files) {
            $data['created_by'] = $userId;
            $invoice = ProjectInvoice::create($data);
            $this->storeFiles($invoice, $files);
            return $invoice->load(['project:id,name,code', 'creator:id,first_name,last_name', 'files']);
        });
    }

    public function update(int $id, array $data, array $files = []): ProjectInvoice
    {
        return DB::transaction(function () use ($id, $data, $files) {
            $invoice = ProjectInvoice::findOrFail($id);
            $invoice->update($data);
            $this->storeFiles($invoice, $files);
            return $invoice->load(['project:id,name,code', 'creator:id,first_name,last_name', 'files']);
        });
    }

    public function delete(int $id): void
    {
        $invoice = ProjectInvoice::findOrFail($id);
        $invoice->delete();
    }

    public function getOne(int $id): ProjectInvoice
    {
        return ProjectInvoice::with([
            'project:id,name,code',
            'creator:id,first_name,last_name',
            'files',
            'payments',
        ])->findOrFail($id);
    }

    public function createPayment(int $invoiceId, array $data, int $userId): ProjectInvoicePayment
    {
        $invoice = ProjectInvoice::findOrFail($invoiceId);
        $data['created_by'] = $userId;

        return $invoice->payments()->create($data);
    }

    public function deletePayment(int $paymentId): void
    {
        ProjectInvoicePayment::findOrFail($paymentId)->delete();
    }

    public function storeFilesForInvoice(int $id, array $files): ProjectInvoice
    {
        $invoice = ProjectInvoice::findOrFail($id);
        $this->storeFiles($invoice, $files);
        return $invoice->load(['project:id,name,code', 'creator:id,first_name,last_name', 'files']);
    }

    public function downloadFile(int $fileId)
    {
        $file = ProjectInvoiceFile::findOrFail($fileId);
        return Storage::disk('local')->download($file->file_path, $file->file_name);
    }

    public function deleteFile(int $fileId): void
    {
        $file = ProjectInvoiceFile::findOrFail($fileId);
        Storage::disk('local')->delete($file->file_path);
        $file->delete();
    }

    private function storeFiles(ProjectInvoice $invoice, array $files): void
    {
        foreach ($files as $file) {
            $path = $file->store('projects/invoices', 'local');
            $invoice->files()->create([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }
}
