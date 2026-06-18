<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyDocument;
use App\Services\DocumentLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyDocumentController extends Controller
{
    public function __construct(private DocumentLibraryService $documentLibraryService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);
        $filters = $request->only(['search', 'doc_type', 'status']);

        return $this->success($this->documentLibraryService->list($filters, $perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'doc_type' => ['nullable', 'in:contract,tender,sst,policy,procedure,other'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:draft,published,archived'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:51200'],
        ]);

        $document = $this->documentLibraryService->create(
            $validated,
            $request->file('file'),
            $request->user()->id
        );

        return $this->created($document, 'Document uploaded.');
    }

    public function show(int $id): JsonResponse
    {
        $document = CompanyDocument::with('uploader:id,first_name,last_name')->findOrFail($id);

        return $this->success($document);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'doc_type' => ['sometimes', 'in:contract,tender,sst,policy,procedure,other'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:draft,published,archived'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:51200'],
        ]);

        unset($validated['file']);

        $document = $this->documentLibraryService->update($id, $validated, $request->file('file'));

        return $this->success($document, 'Document updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->documentLibraryService->delete($id);

        return $this->success(null, 'Document deleted.');
    }

    public function download(int $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->documentLibraryService->download($id);
    }
}
