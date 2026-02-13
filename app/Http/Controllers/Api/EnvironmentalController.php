<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EnvironmentalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnvironmentalController extends Controller
{
    public function __construct(private EnvironmentalService $environmentalService) {}

    // ── Overview Stats ──

    public function overview(Request $request): JsonResponse
    {
        $stats = $this->environmentalService->getOverviewStats($request->integer('project_id') ?: null);
        return $this->success($stats);
    }

    // ── Waste Records ──

    public function wasteRecords(Request $request): JsonResponse
    {
        $filters = $request->only(['project_id', 'status', 'waste_type', 'search']);
        return $this->success($this->environmentalService->listWaste($filters, $request->integer('per_page', 15)));
    }

    public function storeWaste(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'waste_type' => ['required', 'in:general,hazardous,recyclable,construction_debris,chemical,organic,electronic,other'],
            'description' => ['required', 'string'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit' => ['required', 'string', 'max:50'],
            'disposal_method' => ['nullable', 'string', 'max:255'],
            'disposal_date' => ['nullable', 'date'],
            'hauler' => ['nullable', 'string', 'max:255'],
            'manifest_number' => ['nullable', 'string', 'max:100'],
            'destination' => ['nullable', 'string', 'max:255'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['image', 'max:5120'],
        ]);

        $photos = $request->file('photos', []);
        unset($validated['photos']);

        $record = $this->environmentalService->createWaste($validated, $request->user()->id, $photos);
        return $this->created($record, 'Waste record created successfully.');
    }

    public function updateWaste(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'waste_type' => ['sometimes', 'in:general,hazardous,recyclable,construction_debris,chemical,organic,electronic,other'],
            'description' => ['sometimes', 'string'],
            'quantity' => ['sometimes', 'numeric', 'min:0.01'],
            'unit' => ['sometimes', 'string', 'max:50'],
            'disposal_method' => ['nullable', 'string', 'max:255'],
            'disposal_date' => ['nullable', 'date'],
            'hauler' => ['nullable', 'string', 'max:255'],
            'manifest_number' => ['nullable', 'string', 'max:100'],
            'destination' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:pending,collected,disposed,verified'],
        ]);

        return $this->success($this->environmentalService->updateWaste($id, $validated));
    }

    // ── Site Inspections ──

    public function inspections(Request $request): JsonResponse
    {
        $filters = $request->only(['project_id', 'type', 'overall_status', 'search']);
        return $this->success($this->environmentalService->listInspections($filters, $request->integer('per_page', 15)));
    }

    public function storeInspection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'inspection_date' => ['required', 'date'],
            'type' => ['required', 'in:routine,follow_up,complaint,regulatory,pre_construction,other'],
            'findings' => ['required', 'string'],
            'recommendations' => ['nullable', 'string'],
            'overall_status' => ['required', 'in:satisfactory,needs_improvement,unsatisfactory'],
            'follow_up_required' => ['boolean'],
            'follow_up_date' => ['nullable', 'date'],
            'corrective_actions' => ['nullable', 'string'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['image', 'max:5120'],
        ]);

        $photos = $request->file('photos', []);
        unset($validated['photos']);

        $inspection = $this->environmentalService->createInspection($validated, $request->user()->id, $photos);
        return $this->created($inspection, 'Inspection recorded successfully.');
    }

    public function showInspection(int $id): JsonResponse
    {
        return $this->success($this->environmentalService->getInspection($id));
    }

    public function updateInspection(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'findings' => ['sometimes', 'string'],
            'recommendations' => ['nullable', 'string'],
            'overall_status' => ['sometimes', 'in:satisfactory,needs_improvement,unsatisfactory'],
            'follow_up_required' => ['boolean'],
            'follow_up_date' => ['nullable', 'date'],
            'corrective_actions' => ['nullable', 'string'],
        ]);

        return $this->success($this->environmentalService->updateInspection($id, $validated));
    }

    // ── Environmental Audits ──

    public function audits(Request $request): JsonResponse
    {
        $filters = $request->only(['project_id', 'status', 'type', 'search']);
        return $this->success($this->environmentalService->listAudits($filters, $request->integer('per_page', 15)));
    }

    public function storeAudit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'audit_date' => ['required', 'date'],
            'type' => ['required', 'in:internal,external,regulatory'],
            'scope' => ['nullable', 'string'],
            'findings' => ['required', 'string'],
            'non_conformities' => ['nullable', 'string'],
            'corrective_actions' => ['nullable', 'string'],
            'status' => ['required', 'in:scheduled,in_progress,completed,closed'],
            'next_audit_date' => ['nullable', 'date'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['image', 'max:5120'],
        ]);

        $photos = $request->file('photos', []);
        unset($validated['photos']);

        $audit = $this->environmentalService->createAudit($validated, $request->user()->id, $photos);
        return $this->created($audit, 'Audit record created successfully.');
    }

    public function showAudit(int $id): JsonResponse
    {
        return $this->success($this->environmentalService->getAudit($id));
    }

    public function updateAudit(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'findings' => ['sometimes', 'string'],
            'non_conformities' => ['nullable', 'string'],
            'corrective_actions' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:scheduled,in_progress,completed,closed'],
            'next_audit_date' => ['nullable', 'date'],
        ]);

        return $this->success($this->environmentalService->updateAudit($id, $validated));
    }

    // ── PDF Reports ──

    public function inspectionPdf(int $id)
    {
        return $this->environmentalService->generateInspectionPdf($id)->download("inspection-report-{$id}.pdf");
    }

    public function auditPdf(int $id)
    {
        return $this->environmentalService->generateAuditPdf($id)->download("audit-report-{$id}.pdf");
    }

    // ── Photo Upload ──

    public function uploadPhotos(Request $request, string $type, int $id): JsonResponse
    {
        $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['image', 'max:5120'],
        ]);

        $modelMap = [
            'waste' => \App\Models\WasteRecord::class,
            'inspections' => \App\Models\SiteInspection::class,
            'audits' => \App\Models\EnvironmentalAudit::class,
        ];

        if (!isset($modelMap[$type])) {
            return $this->error('Invalid resource type.', 422);
        }

        $model = $modelMap[$type]::findOrFail($id);
        $this->environmentalService->uploadPhotos($model, $request->file('photos'));

        return $this->success($model->load('photos'), 'Photos uploaded successfully.');
    }
}
