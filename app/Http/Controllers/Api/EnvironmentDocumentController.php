<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnvironmentDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EnvironmentDocumentController extends Controller
{
    public const CATEGORIES = [
        'env-report', 'env-monitoring', 'bmp-inspections', 'scheduled-waste',
        'gse-diesel', 'training-awareness', 'doe-compliance', 'permits-certificates',
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $query = EnvironmentDocument::query()->where('category', $validated['category'])->latest();
        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(fn ($builder) => $builder
                ->where('ref_number', 'like', "%{$search}%")
                ->orWhere('document_title', 'like', "%{$search}%"));
        }

        return response()->json($query->paginate(20)->through(fn (EnvironmentDocument $document) => [
            'id' => $document->id,
            'category' => $document->category,
            'ref_number' => $document->ref_number,
            'document_title' => $document->document_title,
            'file_name' => $document->file_name,
            'uploaded_at' => $document->created_at,
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'ref_number' => ['nullable', 'string', 'max:100'],
            'document_title' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'extensions:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:20480'],
        ]);

        $file = $validated['file'];
        // Keep compliance records private; downloads are served through this permission-gated endpoint.
        $path = $file->store('environment-documents/'.$validated['category'], 'local');
        $document = EnvironmentDocument::create([
            'category' => $validated['category'],
            'ref_number' => $validated['ref_number'] ?? null,
            'document_title' => $validated['document_title'],
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Document uploaded.', 'data' => $document], 201);
    }

    public function download(EnvironmentDocument $document): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return response()->download(Storage::disk('local')->path($document->file_path), $document->file_name);
    }

    public function destroy(EnvironmentDocument $document): JsonResponse
    {
        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return response()->json(['message' => 'Document deleted.']);
    }
}
