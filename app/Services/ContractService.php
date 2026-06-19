<?php

namespace App\Services;

use App\Models\ProjectContract;
use App\Models\ProjectContractFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ContractService
{
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = ProjectContract::with([
            'project:id,name,code',
            'creator:id,first_name,last_name',
            'files',
        ])->orderByDesc('created_at');

        if (!empty($filters['project_id'])) $query->forProject($filters['project_id']);
        if (!empty($filters['status'])) $query->byStatus($filters['status']);
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")
                ->orWhere('contract_no', 'like', "%{$search}%")
                ->orWhere('pic_name', 'like', "%{$search}%"));
        }

        return $query->paginate($perPage);
    }

    public function getOne(int $id): ProjectContract
    {
        return ProjectContract::with([
            'project:id,name,code',
            'creator:id,first_name,last_name',
            'files',
        ])->findOrFail($id);
    }

    public function create(array $data, int $userId, array $files = []): ProjectContract
    {
        return DB::transaction(function () use ($data, $userId, $files) {
            $data['created_by'] = $userId;
            $contract = ProjectContract::create($data);

            $this->storeFiles($contract, $files);

            return $this->getOne($contract->id);
        });
    }

    public function update(int $id, array $data, array $files = []): ProjectContract
    {
        return DB::transaction(function () use ($id, $data, $files) {
            $contract = ProjectContract::findOrFail($id);
            $contract->update($data);

            $this->storeFiles($contract, $files);

            return $this->getOne($contract->id);
        });
    }

    public function delete(int $id): void
    {
        $contract = ProjectContract::with('files')->findOrFail($id);

        foreach ($contract->files as $file) {
            Storage::disk('local')->delete($file->file_path);
        }

        $contract->delete();
    }

    public function addFiles(int $id, array $files = []): ProjectContract
    {
        $contract = ProjectContract::findOrFail($id);
        $this->storeFiles($contract, $files);

        return $this->getOne($contract->id);
    }

    public function deleteFile(int $fileId): void
    {
        $file = ProjectContractFile::findOrFail($fileId);
        Storage::disk('local')->delete($file->file_path);
        $file->delete();
    }

    public function downloadFile(int $fileId)
    {
        $file = ProjectContractFile::findOrFail($fileId);

        return Storage::disk('local')->download($file->file_path, $file->file_name);
    }

    private function storeFiles(ProjectContract $contract, array $files): void
    {
        foreach ($files as $file) {
            if (!$file) continue;
            $path = $file->store('projects/contracts', 'local');
            $contract->files()->create([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }
}
