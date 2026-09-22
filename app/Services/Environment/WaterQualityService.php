<?php

namespace App\Services\Environment;

use App\Models\WaterQualityRecord;
use Illuminate\Pagination\LengthAwarePaginator;

class WaterQualityService
{
    public function list(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);

        $query = WaterQualityRecord::with(['project:id,name,code', 'creator:id,first_name,last_name'])
            ->orderByDesc('sample_date')
            ->orderByDesc('id');

        if (! empty($filters['project_id'])) {
            $query->forProject($filters['project_id']);
        }
        if (! empty($filters['from']) || ! empty($filters['to'])) {
            $query->between($filters['from'] ?? null, $filters['to'] ?? null);
        }
        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        return $query->paginate($perPage);
    }

    public function create(array $data, int $userId): WaterQualityRecord
    {
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        return WaterQualityRecord::create($data)->load(['project:id,name,code', 'creator:id,first_name,last_name']);
    }

    public function update(WaterQualityRecord $record, array $data, int $userId): WaterQualityRecord
    {
        $data['updated_by'] = $userId;
        $record->update($data);

        return $record->load(['project:id,name,code', 'creator:id,first_name,last_name']);
    }

    public function delete(WaterQualityRecord $record): void
    {
        $record->delete();
    }

    /**
     * Points (W1..W4) that have at least one non-empty in-situ value recorded.
     */
    public function pointsSampled(WaterQualityRecord $record): array
    {
        $insitu = $record->insitu ?? [];

        return array_values(array_filter(WaterQualityRecord::POINTS, function ($point) use ($insitu) {
            $values = $insitu[$point] ?? [];

            return is_array($values) && count(array_filter($values, fn ($v) => $v !== null && $v !== '')) > 0;
        }));
    }
}
