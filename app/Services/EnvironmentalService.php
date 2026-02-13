<?php

namespace App\Services;

use App\Models\EnvironmentalAudit;
use App\Models\SiteInspection;
use App\Models\WasteRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EnvironmentalService
{
    // ── Waste Records ──

    public function listWaste(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = WasteRecord::with(['project:id,name,code', 'recorder:id,first_name,last_name', 'photos'])
            ->orderByDesc('created_at');

        if (!empty($filters['project_id'])) $query->forProject($filters['project_id']);
        if (!empty($filters['status'])) $query->byStatus($filters['status']);
        if (!empty($filters['waste_type'])) $query->byType($filters['waste_type']);
        if (!empty($filters['search'])) {
            $query->where(fn ($q) => $q->where('description', 'like', "%{$filters['search']}%")
                ->orWhere('hauler', 'like', "%{$filters['search']}%"));
        }

        return $query->paginate($perPage);
    }

    public function createWaste(array $data, int $userId, array $photos = []): WasteRecord
    {
        return DB::transaction(function () use ($data, $userId, $photos) {
            $data['recorded_by'] = $userId;
            $record = WasteRecord::create($data);
            $this->storePhotos($record, $photos);
            return $record->load(['project:id,name', 'recorder:id,first_name,last_name', 'photos']);
        });
    }

    public function updateWaste(int $id, array $data): WasteRecord
    {
        $record = WasteRecord::findOrFail($id);
        $record->update($data);
        return $record->load(['project:id,name', 'recorder:id,first_name,last_name', 'photos']);
    }

    // ── Site Inspections ──

    public function listInspections(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = SiteInspection::with(['project:id,name,code', 'inspector:id,first_name,last_name', 'photos'])
            ->orderByDesc('inspection_date');

        if (!empty($filters['project_id'])) $query->forProject($filters['project_id']);
        if (!empty($filters['type'])) $query->where('type', $filters['type']);
        if (!empty($filters['overall_status'])) $query->where('overall_status', $filters['overall_status']);
        if (!empty($filters['search'])) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$filters['search']}%")
                ->orWhere('findings', 'like', "%{$filters['search']}%"));
        }

        return $query->paginate($perPage);
    }

    public function createInspection(array $data, int $userId, array $photos = []): SiteInspection
    {
        return DB::transaction(function () use ($data, $userId, $photos) {
            $data['inspector_id'] = $userId;
            $inspection = SiteInspection::create($data);
            $this->storePhotos($inspection, $photos);
            return $inspection->load(['project:id,name', 'inspector:id,first_name,last_name', 'photos']);
        });
    }

    public function getInspection(int $id): SiteInspection
    {
        return SiteInspection::with(['project:id,name,code', 'inspector:id,first_name,last_name', 'photos'])->findOrFail($id);
    }

    public function updateInspection(int $id, array $data): SiteInspection
    {
        $inspection = SiteInspection::findOrFail($id);
        $inspection->update($data);
        return $inspection->load(['project:id,name', 'inspector:id,first_name,last_name', 'photos']);
    }

    // ── Audits ──

    public function listAudits(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = EnvironmentalAudit::with(['project:id,name,code', 'auditor:id,first_name,last_name', 'photos'])
            ->orderByDesc('audit_date');

        if (!empty($filters['project_id'])) $query->forProject($filters['project_id']);
        if (!empty($filters['status'])) $query->byStatus($filters['status']);
        if (!empty($filters['type'])) $query->where('type', $filters['type']);
        if (!empty($filters['search'])) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$filters['search']}%")
                ->orWhere('findings', 'like', "%{$filters['search']}%"));
        }

        return $query->paginate($perPage);
    }

    public function createAudit(array $data, int $userId, array $photos = []): EnvironmentalAudit
    {
        return DB::transaction(function () use ($data, $userId, $photos) {
            $data['auditor_id'] = $userId;
            $audit = EnvironmentalAudit::create($data);
            $this->storePhotos($audit, $photos);
            return $audit->load(['project:id,name', 'auditor:id,first_name,last_name', 'photos']);
        });
    }

    public function getAudit(int $id): EnvironmentalAudit
    {
        return EnvironmentalAudit::with(['project:id,name,code', 'auditor:id,first_name,last_name', 'photos'])->findOrFail($id);
    }

    public function updateAudit(int $id, array $data): EnvironmentalAudit
    {
        $audit = EnvironmentalAudit::findOrFail($id);
        $audit->update($data);
        return $audit->load(['project:id,name', 'auditor:id,first_name,last_name', 'photos']);
    }

    // ── Overview Stats ──

    public function getOverviewStats(?int $projectId = null): array
    {
        $wasteQ = WasteRecord::query();
        $inspQ = SiteInspection::query();
        $auditQ = EnvironmentalAudit::query();

        if ($projectId) {
            $wasteQ->forProject($projectId);
            $inspQ->forProject($projectId);
            $auditQ->forProject($projectId);
        }

        return [
            'pending_waste' => (clone $wasteQ)->byStatus('pending')->count(),
            'hazardous_waste_pending' => (clone $wasteQ)->byType('hazardous')->byStatus('pending')->count(),
            'total_waste_quantity' => (clone $wasteQ)->sum('quantity'),
            'inspections_this_month' => (clone $inspQ)->whereMonth('inspection_date', now()->month)->whereYear('inspection_date', now()->year)->count(),
            'unsatisfactory_inspections' => (clone $inspQ)->where('overall_status', 'unsatisfactory')->count(),
            'pending_follow_ups' => (clone $inspQ)->where('follow_up_required', true)->where('follow_up_date', '>=', now())->count(),
            'open_audits' => (clone $auditQ)->whereIn('status', ['scheduled', 'in_progress'])->count(),
            'total_audits' => (clone $auditQ)->count(),
        ];
    }

    // ── PDF ──

    public function generateInspectionPdf(int $id)
    {
        $record = $this->getInspection($id);
        return Pdf::loadView('pdf.environmental-report', ['record' => $record, 'type' => 'Site Inspection Report'])->setPaper('a4');
    }

    public function generateAuditPdf(int $id)
    {
        $record = $this->getAudit($id);
        return Pdf::loadView('pdf.environmental-report', ['record' => $record, 'type' => 'Environmental Audit Report'])->setPaper('a4');
    }

    // ── Photos ──

    public function uploadPhotos($model, array $files): void
    {
        $this->storePhotos($model, $files);
    }

    private function storePhotos($model, array $files): void
    {
        foreach ($files as $file) {
            $path = $file->store('report-photos', 'public');
            $model->photos()->create([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
            ]);
        }
    }
}
