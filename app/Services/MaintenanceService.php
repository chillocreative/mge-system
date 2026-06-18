<?php

namespace App\Services;

use App\Models\MaintenanceLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class MaintenanceService
{
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = MaintenanceLog::with(['maintainable', 'creator:id,first_name,last_name'])
            ->orderByDesc('performed_date');

        if (!empty($filters['status'])) $query->byStatus($filters['status']);
        if (!empty($filters['maintenance_type'])) $query->where('maintenance_type', $filters['maintenance_type']);
        if (!empty($filters['maintainable_type']) && !empty($filters['maintainable_id'])) {
            $query->where('maintainable_type', $filters['maintainable_type'])
                ->where('maintainable_id', $filters['maintainable_id']);
        }
        if (!empty($filters['search'])) {
            $query->where(fn ($q) => $q->where('description', 'like', "%{$filters['search']}%")
                ->orWhere('vendor', 'like', "%{$filters['search']}%"));
        }

        return $query->paginate($perPage);
    }

    public function listForMaintainable(string $type, int $id): Collection
    {
        return MaintenanceLog::where('maintainable_type', $type)
            ->where('maintainable_id', $id)
            ->orderByDesc('performed_date')
            ->get();
    }

    public function create(array $data, int $userId): MaintenanceLog
    {
        $data['created_by'] = $userId;
        $log = MaintenanceLog::create($data);
        return $log->load(['maintainable', 'creator:id,first_name,last_name']);
    }

    public function get(int $id): MaintenanceLog
    {
        return MaintenanceLog::with(['maintainable', 'creator:id,first_name,last_name'])->findOrFail($id);
    }

    public function update(int $id, array $data): MaintenanceLog
    {
        $log = MaintenanceLog::findOrFail($id);
        $log->update($data);
        return $log->load(['maintainable', 'creator:id,first_name,last_name']);
    }

    public function delete(int $id): void
    {
        MaintenanceLog::findOrFail($id)->delete();
    }

    public function upcoming(int $days = 30): Collection
    {
        return MaintenanceLog::with(['maintainable', 'creator:id,first_name,last_name'])
            ->upcoming($days)
            ->orderBy('next_due_date')
            ->get();
    }
}
