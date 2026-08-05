<?php

namespace App\Services;

use App\Models\ContractBoqItem;
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
            'pics',
        ])->orderByDesc('created_at');

        if (!empty($filters['project_id'])) $query->forProject($filters['project_id']);
        if (!empty($filters['status'])) $query->byStatus($filters['status']);
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")
                ->orWhere('contract_no', 'like', "%{$search}%")
                ->orWhereHas('pics', fn ($p) => $p->where('name', 'like', "%{$search}%")));
        }

        return $query->paginate($perPage);
    }

    public function getOne(int $id): ProjectContract
    {
        return ProjectContract::with([
            'project:id,name,code',
            'creator:id,first_name,last_name',
            'files',
            'pics',
        ])->findOrFail($id);
    }

    public function create(array $data, int $userId, array $files = [], ?array $pics = null): ProjectContract
    {
        return DB::transaction(function () use ($data, $userId, $files, $pics) {
            $data['created_by'] = $userId;
            $contract = ProjectContract::create($data);

            $this->storeFiles($contract, $files);
            $this->syncPics($contract, $pics);

            return $this->getOne($contract->id);
        });
    }

    public function update(int $id, array $data, array $files = [], ?array $pics = null): ProjectContract
    {
        return DB::transaction(function () use ($id, $data, $files, $pics) {
            $contract = ProjectContract::findOrFail($id);
            $contract->update($data);

            $this->storeFiles($contract, $files);
            $this->syncPics($contract, $pics);

            return $this->getOne($contract->id);
        });
    }

    /**
     * Replace the contract's correspondence PICs with the provided list.
     * Passing null leaves them unchanged (e.g. file-only updates).
     */
    private function syncPics(ProjectContract $contract, ?array $pics): void
    {
        if ($pics === null) {
            return;
        }

        $contract->pics()->delete();

        foreach (array_values($pics) as $i => $pic) {
            if (empty($pic['name'])) {
                continue;
            }
            $contract->pics()->create([
                'name' => $pic['name'],
                'email' => $pic['email'] ?? null,
                'phone' => $pic['phone'] ?? null,
                'company' => $pic['company'] ?? null,
                'designation' => $pic['designation'] ?? null,
                'sort_order' => $i,
            ]);
        }
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

    public function listBoqItems(int $contractId)
    {
        return ProjectContract::findOrFail($contractId)->boqItems;
    }

    public function createBoqItem(int $contractId, array $data, int $userId): ContractBoqItem
    {
        $contract = ProjectContract::findOrFail($contractId);

        $data['amount'] = round((float) $data['quantity'] * (float) $data['rate'], 2);
        $data['created_by'] = $userId;
        $data['sort_order'] = ((int) $contract->boqItems()->max('sort_order')) + 1;

        return $contract->boqItems()->create($data);
    }

    public function deleteBoqItem(int $itemId): void
    {
        ContractBoqItem::findOrFail($itemId)->delete();
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
