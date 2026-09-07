<?php

namespace App\Services;

use App\Imports\BoqImport;
use App\Models\Attachment;
use App\Models\ContractBoqItem;
use App\Models\ProjectContract;
use App\Models\ProjectContractFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ContractService
{
    public function __construct(private FileUploadService $uploads) {}

    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = ProjectContract::with([
            'project:id,name,code',
            'creator:id,first_name,last_name',
            'files',
            'pics',
        ])->orderByDesc('created_at');

        if (! empty($filters['project_id'])) {
            $query->forProject($filters['project_id']);
        }
        if (! empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }
        if (! empty($filters['search'])) {
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

    /**
     * Import BQ items from an uploaded copy of the official template.
     *
     * Replaces nothing implicitly — items are appended after any existing rows,
     * continuing the sort order, so an import never silently wipes manual entry.
     *
     * @return array{imported: int, skipped: int}
     */
    public function importBoq(int $contractId, \Illuminate\Http\UploadedFile $file, int $userId): array
    {
        $contract = ProjectContract::findOrFail($contractId);

        $import = new BoqImport;
        \Maatwebsite\Excel\Facades\Excel::import($import, $file);

        if ($import->rows === []) {
            return ['imported' => 0, 'skipped' => $import->skipped];
        }

        $offset = (int) ContractBoqItem::where('project_contract_id', $contract->id)->max('sort_order');
        $now = now();

        $payload = array_map(fn (array $r, int $i) => $r + [
            'project_contract_id' => $contract->id,
            'sort_order' => $offset + 1 + $i,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ], $import->rows, array_keys($import->rows));

        ContractBoqItem::insert($payload);

        return ['imported' => count($payload), 'skipped' => $import->skipped];
    }

    public function deleteBoqItem(int $itemId): void
    {
        ContractBoqItem::findOrFail($itemId)->delete();
    }

    // ── Drawings folder store (Ciri 4) — bulk/folder upload via the shared engine ──

    public function listDrawings(int $contractId)
    {
        $contract = ProjectContract::findOrFail($contractId);

        return Attachment::where('attachable_type', $contract->getMorphClass())
            ->where('attachable_id', $contract->id)
            ->with('uploader:id,first_name,last_name')
            ->orderBy('folder_path')
            ->orderBy('original_name')
            ->get()
            ->map(fn (Attachment $a) => [
                'id' => $a->id,
                'name' => $a->original_name,
                'folder' => $a->folder_path,
                'size' => $a->size_bytes,
                'extension' => $a->extension,
                'uploaded_by' => $a->uploader ? trim($a->uploader->first_name.' '.$a->uploader->last_name) : null,
                'uploaded_at' => $a->created_at?->toIso8601String(),
            ]);
    }

    /**
     * Bulk upload — one or many files, optionally with a relative folder path
     * each (from the browser folder picker). Duplicates within the same contract
     * are skipped so re-uploading a folder does not pile up copies.
     *
     * @param  array<int, \Illuminate\Http\UploadedFile>  $files
     * @param  array<int, string|null>  $paths
     * @return array{uploaded: int, skipped: int}
     */
    public function addDrawings(int $contractId, array $files, array $paths, int $userId): array
    {
        $contract = ProjectContract::findOrFail($contractId);
        $uploaded = 0;
        $skipped = 0;

        foreach ($files as $i => $file) {
            $relative = $paths[$i] ?? null;
            // webkitRelativePath includes the filename; keep only the directory.
            $folder = $relative ? trim(dirname(str_replace('\\', '/', $relative)), '/.') : null;

            $before = $contract->id;
            $attachment = $this->uploads->attach($file, $contract, $userId, [
                'folder_path' => $folder ?: null,
                'allowed_extensions' => ['pdf', 'dwg', 'dxf', 'dwf', 'rvt', 'ifc', 'jpg', 'jpeg', 'png', 'tif', 'tiff', 'doc', 'docx', 'xls', 'xlsx'],
                'max_size_kb' => 204800,
                'directory' => 'contract-drawings/'.$contract->id,
                'skip_duplicates' => true,
            ]);

            // attach() returns an existing row (same id already present) when a
            // duplicate is skipped; count it as skipped if it predates this call.
            if ($attachment && $attachment->wasRecentlyCreated) {
                $uploaded++;
            } else {
                $skipped++;
            }
        }

        return ['uploaded' => $uploaded, 'skipped' => $skipped];
    }

    public function downloadDrawing(int $attachmentId)
    {
        $attachment = Attachment::findOrFail($attachmentId);

        abort_unless(
            \Illuminate\Support\Facades\Storage::disk($attachment->disk)->exists($attachment->stored_path),
            404,
        );

        return \Illuminate\Support\Facades\Storage::disk($attachment->disk)
            ->download($attachment->stored_path, $attachment->original_name);
    }

    public function deleteDrawing(int $attachmentId): void
    {
        $this->uploads->remove(Attachment::findOrFail($attachmentId));
    }

    private function storeFiles(ProjectContract $contract, array $files): void
    {
        foreach ($files as $file) {
            if (! $file) {
                continue;
            }
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
