<?php

namespace App\Services;

use App\Models\CompanyDocument;
use App\Models\Drawing;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class DocumentLibraryService
{
    // ── Company Documents ──

    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = CompanyDocument::with('uploader:id,first_name,last_name')
            ->orderByDesc('created_at');

        if (!empty($filters['search'])) $query->search($filters['search']);
        if (!empty($filters['doc_type'])) $query->byType($filters['doc_type']);
        if (!empty($filters['status'])) $query->where('status', $filters['status']);

        return $query->paginate($perPage);
    }

    public function create(array $data, UploadedFile $file, int $userId): CompanyDocument
    {
        $path = $file->store('documents/company', 'local');

        $document = CompanyDocument::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'doc_type' => $data['doc_type'] ?? 'other',
            'reference_no' => $data['reference_no'] ?? null,
            'status' => $data['status'] ?? 'published',
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $userId,
        ]);

        return $document->load('uploader:id,first_name,last_name');
    }

    public function update(int $id, array $data, ?UploadedFile $file = null): CompanyDocument
    {
        $document = CompanyDocument::findOrFail($id);

        if ($file) {
            Storage::disk('local')->delete($document->file_path);
            $data['file_path'] = $file->store('documents/company', 'local');
            $data['file_name'] = $file->getClientOriginalName();
            $data['file_type'] = $file->getClientMimeType();
            $data['file_size'] = $file->getSize();
            $data['version'] = $document->version + 1;
        }

        $document->update($data);

        return $document->load('uploader:id,first_name,last_name');
    }

    public function delete(int $id): void
    {
        $document = CompanyDocument::findOrFail($id);
        Storage::disk('local')->delete($document->file_path);
        $document->delete();
    }

    public function download(int $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $document = CompanyDocument::findOrFail($id);
        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    // ── Drawings ──

    public function listDrawings(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Drawing::with(['uploader:id,first_name,last_name', 'project:id,name'])
            ->orderByDesc('created_at');

        if (!empty($filters['search'])) $query->search($filters['search']);
        if (!empty($filters['discipline'])) $query->byDiscipline($filters['discipline']);
        if (!empty($filters['tag'])) $query->where('tag', $filters['tag']);
        if (!empty($filters['status'])) $query->where('status', $filters['status']);

        return $query->paginate($perPage);
    }

    public function createDrawing(array $data, UploadedFile $file, int $userId): Drawing
    {
        $path = $file->store('documents/drawings', 'local');

        $drawing = Drawing::create([
            'title' => $data['title'],
            'drawing_no' => $data['drawing_no'],
            'reference_no' => $data['reference_no'] ?? null,
            'revision' => $data['revision'] ?? null,
            'tag' => $data['tag'] ?? null,
            'discipline' => $data['discipline'] ?? 'civil',
            'project_id' => $data['project_id'] ?? null,
            'status' => $data['status'] ?? 'published',
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $userId,
        ]);

        return $drawing->load(['uploader:id,first_name,last_name', 'project:id,name']);
    }

    public function updateDrawing(int $id, array $data, ?UploadedFile $file = null): Drawing
    {
        $drawing = Drawing::findOrFail($id);

        if ($file) {
            Storage::disk('local')->delete($drawing->file_path);
            $data['file_path'] = $file->store('documents/drawings', 'local');
            $data['file_name'] = $file->getClientOriginalName();
            $data['file_type'] = $file->getClientMimeType();
            $data['file_size'] = $file->getSize();
        }

        $drawing->update($data);

        return $drawing->load(['uploader:id,first_name,last_name', 'project:id,name']);
    }

    public function deleteDrawing(int $id): void
    {
        $drawing = Drawing::findOrFail($id);
        Storage::disk('local')->delete($drawing->file_path);
        $drawing->delete();
    }

    public function downloadDrawing(int $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $drawing = Drawing::findOrFail($id);
        return Storage::disk('local')->download($drawing->file_path, $drawing->file_name);
    }
}
