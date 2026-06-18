<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Drawing;
use App\Services\DocumentLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DrawingController extends Controller
{
    public function __construct(private DocumentLibraryService $documentLibraryService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);
        $filters = $request->only(['search', 'discipline', 'tag', 'status']);

        return $this->success($this->documentLibraryService->listDrawings($filters, $perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'drawing_no' => ['required', 'string', 'max:255', 'unique:drawings,drawing_no'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'revision' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:255'],
            'discipline' => ['nullable', 'in:architectural,structural,civil,mechanical,electrical,other'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'status' => ['nullable', 'in:draft,published,archived'],
            'file' => ['required', 'file', 'mimes:dwg,dxf,pdf,png,jpg,jpeg', 'max:102400'],
        ]);

        $drawing = $this->documentLibraryService->createDrawing(
            $validated,
            $request->file('file'),
            $request->user()->id
        );

        return $this->created($drawing, 'Drawing uploaded.');
    }

    public function show(int $id): JsonResponse
    {
        $drawing = Drawing::with(['uploader:id,first_name,last_name', 'project:id,name'])->findOrFail($id);

        return $this->success($drawing);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'drawing_no' => ['sometimes', 'string', 'max:255', 'unique:drawings,drawing_no,' . $id],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'revision' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:255'],
            'discipline' => ['sometimes', 'in:architectural,structural,civil,mechanical,electrical,other'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'status' => ['sometimes', 'in:draft,published,archived'],
            'file' => ['nullable', 'file', 'mimes:dwg,dxf,pdf,png,jpg,jpeg', 'max:102400'],
        ]);

        unset($validated['file']);

        $drawing = $this->documentLibraryService->updateDrawing($id, $validated, $request->file('file'));

        return $this->success($drawing, 'Drawing updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->documentLibraryService->deleteDrawing($id);

        return $this->success(null, 'Drawing deleted.');
    }

    public function download(int $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->documentLibraryService->downloadDrawing($id);
    }
}
