<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class AssetService
{
    public function listVehicles(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Vehicle::with(['assignedTo:id,first_name,last_name,employee_no'])
            ->withCount('documents')
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }
        if (! empty($filters['type'])) {
            $query->byType($filters['type']);
        }
        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        return $query->paginate($perPage);
    }

    public function createVehicle(array $data, int $userId): Vehicle
    {
        $data['created_by'] = $userId;
        $vehicle = Vehicle::create($data);

        return $vehicle->load(['assignedTo:id,first_name,last_name,employee_no']);
    }

    public function getVehicle(int $id): Vehicle
    {
        return Vehicle::with([
            'assignedTo:id,first_name,last_name,employee_no',
            'creator:id,first_name,last_name',
            'documents' => fn ($q) => $q->orderByDesc('expiry_date'),
            'maintenanceLogs' => fn ($q) => $q->orderByDesc('performed_date'),
        ])->findOrFail($id);
    }

    public function updateVehicle(int $id, array $data): Vehicle
    {
        $vehicle = Vehicle::findOrFail($id);
        $vehicle->update($data);

        return $vehicle->load(['assignedTo:id,first_name,last_name,employee_no']);
    }

    public function deleteVehicle(int $id): void
    {
        Vehicle::findOrFail($id)->delete();
    }

    // ── Documents ──

    public function addDocument(int $vehicleId, array $data, ?UploadedFile $file = null): VehicleDocument
    {
        $vehicle = Vehicle::findOrFail($vehicleId);
        $data['vehicle_id'] = $vehicle->id;

        if ($file) {
            $data['file_path'] = $file->store('vehicles/documents', 'local');
            $data['file_name'] = $file->getClientOriginalName();
        }

        return VehicleDocument::create($data);
    }

    public function deleteDocument(int $vehicleId, int $documentId): void
    {
        $document = VehicleDocument::where('vehicle_id', $vehicleId)->findOrFail($documentId);

        if ($document->file_path) {
            Storage::disk('local')->delete($document->file_path);
        }

        $document->delete();
    }

    public function getDocument(int $vehicleId, int $documentId): VehicleDocument
    {
        return VehicleDocument::where('vehicle_id', $vehicleId)->findOrFail($documentId);
    }

    // ── Expiring documents (across all vehicles) ──

    public function expiringDocuments(int $days = 30): Collection
    {
        return VehicleDocument::with('vehicle:id,registration_no,make,model')
            ->expiringWithin($days)
            ->orderBy('expiry_date')
            ->get();
    }

    public function dashboard(int $days = 30): array
    {
        $expiring = $this->expiringDocuments($days);

        return [
            'total_vehicles' => Vehicle::count(),
            'active_vehicles' => Vehicle::byStatus('active')->count(),
            'expiring_count' => $expiring->count(),
            'expiring_road_tax' => $expiring->where('doc_type', 'road_tax')->count(),
            'expiring_insurance' => $expiring->where('doc_type', 'insurance')->count(),
            'expiring_documents' => $expiring->values(),
        ];
    }
}
