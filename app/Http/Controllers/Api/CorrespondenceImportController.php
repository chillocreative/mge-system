<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CorrespondenceWorkbookImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CorrespondenceImportController extends Controller
{
    public function __construct(private CorrespondenceWorkbookImportService $service) {}

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx,xls', 'max:20480'],
            'project_id' => ['nullable', 'exists:projects,id'],
        ]);
        $file = $request->file('file');

        return $this->success(
            $this->service->preview(
                $file->getRealPath(),
                isset($data['project_id']) ? (int) $data['project_id'] : null,
                $file->getClientOriginalName(),
            ),
            'Correspondence workbook preview generated.',
        );
    }

    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx,xls', 'max:20480'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'confirmed' => ['required', 'accepted'],
        ]);
        $file = $request->file('file');

        return $this->success(
            $this->service->import(
                $file->getRealPath(),
                (int) $request->user()->id,
                isset($data['project_id']) ? (int) $data['project_id'] : null,
                $file->getClientOriginalName(),
            ),
            'Correspondence workbook imported.',
        );
    }
}
