<?php

namespace App\Services;

use App\Models\CompanyEvent;
use Illuminate\Support\Collection;

class CalendarService
{
    /**
     * Return all events within the given range (NOT paginated).
     *
     * @param  array{start?:string,end?:string,type?:string}  $filters
     */
    public function list(array $filters): Collection
    {
        $query = CompanyEvent::with([
            'creator:id,first_name,last_name',
            'employee:id,first_name,last_name',
            'project:id,name',
        ])->orderBy('start_datetime');

        if (!empty($filters['start']) && !empty($filters['end'])) {
            $query->forRange($filters['start'], $filters['end']);
        }

        if (!empty($filters['type'])) {
            $query->byType($filters['type']);
        }

        return $query->get();
    }

    public function create(array $data, int $userId): CompanyEvent
    {
        $data['created_by'] = $userId;
        $data['source'] = $data['source'] ?? 'app';

        $event = CompanyEvent::create($data);

        return $event->load([
            'creator:id,first_name,last_name',
            'employee:id,first_name,last_name',
            'project:id,name',
        ]);
    }

    public function update(int $id, array $data): CompanyEvent
    {
        $event = CompanyEvent::findOrFail($id);
        $event->update($data);

        return $event->load([
            'creator:id,first_name,last_name',
            'employee:id,first_name,last_name',
            'project:id,name',
        ]);
    }

    public function delete(int $id): void
    {
        CompanyEvent::findOrFail($id)->delete();
    }
}
