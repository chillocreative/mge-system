<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectDocumentController extends Controller
{
    public function index(int $projectId, Request $request): JsonResponse
    {
        $documents = ProjectDocument::where('project_id', $projectId)
            ->with('uploader:id,first_name,last_name')
            ->when($request->category, fn ($q, $c) => $q->byCategory($c))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->success($documents);
    }

    public function store(int $projectId, Request $request): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file' => ['required', 'file', 'max:51200'], // 50MB max
            'category' => ['nullable', 'in:drawing,contract,permit,report,photo,specification,invoice,other'],
        ]);

        $file = $request->file('file');
        $path = $file->store("projects/{$project->id}/documents", 'public');

        $document = ProjectDocument::create([
            'project_id' => $project->id,
            'title' => $request->title,
            'description' => $request->description,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'category' => $request->category ?? 'other',
            'uploaded_by' => $request->user()->id,
        ]);

        return $this->created($document->load('uploader:id,first_name,last_name'), 'Document uploaded.');
    }

    public function show(int $projectId, int $documentId): JsonResponse
    {
        $document = ProjectDocument::where('project_id', $projectId)
            ->with('uploader:id,first_name,last_name')
            ->findOrFail($documentId);

        return $this->success($document);
    }

    public function destroy(int $projectId, int $documentId): JsonResponse
    {
        $document = ProjectDocument::where('project_id', $projectId)->findOrFail($documentId);

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return $this->success(null, 'Document deleted.');
    }

    public function download(int $projectId, int $documentId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $document = ProjectDocument::where('project_id', $projectId)->findOrFail($documentId);

        return Storage::disk('public')->download($document->file_path, $document->file_name);
    }
}
