<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CorrespondenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CorrespondenceController extends Controller
{
    public function __construct(private CorrespondenceService $correspondenceService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['project_id', 'type', 'status', 'search']);
        $perPage = min($request->integer('per_page', 15), 100);

        return $this->success($this->correspondenceService->list($filters, $perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'type' => ['required', 'exists:correspondence_types,code'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:open,pending,closed'],
            'raised_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'response' => ['nullable', 'string'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:25600', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
        ]);

        $files = $request->file('files', []);
        unset($validated['files']);

        $correspondence = $this->correspondenceService->create($validated, $request->user()->id, $files);

        return $this->created($correspondence, 'Correspondence created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        return $this->success($this->correspondenceService->getOne($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['sometimes', 'exists:projects,id'],
            'type' => ['sometimes', 'exists:correspondence_types,code'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:open,pending,closed'],
            'raised_date' => ['sometimes', 'date'],
            'due_date' => ['nullable', 'date'],
            'response' => ['nullable', 'string'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:25600', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
        ]);

        $files = $request->file('files', []);
        unset($validated['files']);

        return $this->success($this->correspondenceService->update($id, $validated, $files), 'Correspondence updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->correspondenceService->delete($id);

        return $this->success(null, 'Correspondence deleted successfully.');
    }

    public function storeFiles(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['file', 'max:25600', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
        ]);

        $correspondence = $this->correspondenceService->update($id, [], $request->file('files'));

        return $this->success($correspondence, 'Files uploaded successfully.');
    }

    public function downloadFile(int $fileId)
    {
        return $this->correspondenceService->downloadFile($fileId);
    }

    public function destroyFile(int $fileId): JsonResponse
    {
        $this->correspondenceService->deleteFile($fileId);

        return $this->success(null, 'File deleted.');
    }
}
