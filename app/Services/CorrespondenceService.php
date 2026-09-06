<?php

namespace App\Services;

use App\Models\ProjectCorrespondence;
use App\Models\ProjectCorrespondenceFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CorrespondenceService
{
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = ProjectCorrespondence::with(['project:id,name,code', 'creator:id,first_name,last_name', 'files'])
            ->orderByDesc('raised_date')
            ->orderByDesc('id');

        if (! empty($filters['project_id'])) {
            $query->forProject($filters['project_id']);
        }
        if (! empty($filters['type'])) {
            $query->byType($filters['type']);
        }
        if (! empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }
        if (! empty($filters['search'])) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$filters['search']}%")
                ->orWhere('reference_no', 'like', "%{$filters['search']}%")
                ->orWhere('description', 'like', "%{$filters['search']}%"));
        }

        return $query->paginate($perPage);
    }

    public function create(array $data, int $userId, array $files = []): ProjectCorrespondence
    {
        return DB::transaction(function () use ($data, $userId, $files) {
            $data['created_by'] = $userId;
            $correspondence = ProjectCorrespondence::create($data);
            $this->storeFiles($correspondence, $files);

            return $correspondence->load(['project:id,name,code', 'creator:id,first_name,last_name', 'files']);
        });
    }

    public function update(int $id, array $data, array $files = []): ProjectCorrespondence
    {
        return DB::transaction(function () use ($id, $data, $files) {
            $correspondence = ProjectCorrespondence::findOrFail($id);
            $correspondence->update($data);
            $this->storeFiles($correspondence, $files);

            return $correspondence->load(['project:id,name,code', 'creator:id,first_name,last_name', 'files']);
        });
    }

    public function delete(int $id): void
    {
        $correspondence = ProjectCorrespondence::with('files')->findOrFail($id);
        foreach ($correspondence->files as $file) {
            Storage::disk('local')->delete($file->file_path);
        }
        $correspondence->files()->delete();
        $correspondence->delete();
    }

    public function getOne(int $id): ProjectCorrespondence
    {
        return ProjectCorrespondence::with(['project:id,name,code', 'creator:id,first_name,last_name', 'files'])->findOrFail($id);
    }

    public function downloadFile(int $fileId)
    {
        $file = ProjectCorrespondenceFile::findOrFail($fileId);

        return Storage::disk('local')->download($file->file_path, $file->file_name);
    }

    public function deleteFile(int $fileId): void
    {
        $file = ProjectCorrespondenceFile::findOrFail($fileId);
        Storage::disk('local')->delete($file->file_path);
        $file->delete();
    }

    private function storeFiles(ProjectCorrespondence $correspondence, array $files): void
    {
        foreach ($files as $file) {
            $path = $file->store('projects/correspondence', 'local');
            $correspondence->files()->create([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }
}
