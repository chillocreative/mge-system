<?php

namespace App\Services;

use App\Models\ProjectInvoice;
use App\Models\ProjectInvoiceFile;
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
        ])->orderByDesc('invoice_date');

        if (!empty($filters['project_id'])) $query->forProject($filters['project_id']);
        if (!empty($filters['status'])) $query->byStatus($filters['status']);
        if (!empty($filters['search'])) {
            $query->where(fn ($q) => $q->where('invoice_no', 'like', "%{$filters['search']}%")
                ->orWhere('notes', 'like', "%{$filters['search']}%"));
        }

        return $query->paginate($perPage);
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
        ])->findOrFail($id);
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
