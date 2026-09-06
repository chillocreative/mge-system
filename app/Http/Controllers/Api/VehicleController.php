<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VehicleController extends Controller
{
    public function __construct(private AssetService $assetService) {}

    // ── Vehicles ──

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'type', 'search']);
        $perPage = min($request->integer('per_page', 15), 100);

        return $this->success($this->assetService->listVehicles($filters, $perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'registration_no' => ['required', 'string', 'max:255', 'unique:vehicles,registration_no'],
            'make' => ['required', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'type' => ['required', 'in:car,van,truck,lorry,machinery,other'],
            'purchase_date' => ['nullable', 'date'],
            'current_value' => ['nullable', 'numeric', 'min:0'],
            'assigned_to' => ['nullable', 'exists:employees,id'],
            'status' => ['nullable', 'in:active,inactive,disposed'],
            'notes' => ['nullable', 'string'],
        ]);

        $vehicle = $this->assetService->createVehicle($validated, $request->user()->id);

        return $this->created($vehicle, 'Vehicle added successfully.');
    }

    public function show(int $id): JsonResponse
    {
        return $this->success($this->assetService->getVehicle($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'registration_no' => ['sometimes', 'string', 'max:255', 'unique:vehicles,registration_no,'.$id],
            'make' => ['sometimes', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'type' => ['sometimes', 'in:car,van,truck,lorry,machinery,other'],
            'purchase_date' => ['nullable', 'date'],
            'current_value' => ['nullable', 'numeric', 'min:0'],
            'assigned_to' => ['nullable', 'exists:employees,id'],
            'status' => ['sometimes', 'in:active,inactive,disposed'],
            'notes' => ['nullable', 'string'],
        ]);

        return $this->success($this->assetService->updateVehicle($id, $validated), 'Vehicle updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->assetService->deleteVehicle($id);

        return $this->success(null, 'Vehicle deleted.');
    }

    // ── Documents ──

    public function storeDocument(Request $request, int $vehicleId): JsonResponse
    {
        $validated = $request->validate([
            'doc_type' => ['required', 'in:road_tax,insurance,permit,other'],
            'provider' => ['nullable', 'string', 'max:255'],
            'policy_or_ref_no' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $file = $request->file('file');
        unset($validated['file']);

        $document = $this->assetService->addDocument($vehicleId, $validated, $file);

        return $this->created($document, 'Document added.');
    }

    public function destroyDocument(int $vehicleId, int $documentId): JsonResponse
    {
        $this->assetService->deleteDocument($vehicleId, $documentId);

        return $this->success(null, 'Document deleted.');
    }

    public function downloadDocument(int $vehicleId, int $documentId)
    {
        $document = $this->assetService->getDocument($vehicleId, $documentId);

        if (! $document->file_path || ! Storage::disk('local')->exists($document->file_path)) {
            return $this->notFound('File not found.');
        }

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    // ── Expiring documents / dashboard ──

    public function expiring(Request $request): JsonResponse
    {
        $days = min($request->integer('days', 30), 365);

        return $this->success($this->assetService->dashboard($days));
    }
}
