<?php

namespace App\Services;

use App\Models\MeetingActionItem;
use App\Models\MeetingMinute;
use App\Models\MeetingMinuteFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MeetingService
{
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = MeetingMinute::with([
            'creator:id,first_name,last_name',
            'project:id,name,code',
            'files',
            'actionItems',
        ])->orderByDesc('meeting_date');

        if (!empty($filters['status'])) $query->byStatus($filters['status']);
        if (!empty($filters['date_from'])) $query->whereDate('meeting_date', '>=', $filters['date_from']);
        if (!empty($filters['date_to'])) $query->whereDate('meeting_date', '<=', $filters['date_to']);
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%")
                ->orWhere('agenda', 'like', "%{$search}%"));
        }

        return $query->paginate($perPage);
    }

    public function getOne(int $id): MeetingMinute
    {
        return MeetingMinute::with([
            'creator:id,first_name,last_name',
            'project:id,name,code',
            'files',
            'actionItems.assignee:id,first_name,last_name,employee_no',
        ])->findOrFail($id);
    }

    public function create(array $data, int $userId, array $files = []): MeetingMinute
    {
        return DB::transaction(function () use ($data, $userId, $files) {
            $actionItems = $data['action_items'] ?? [];
            unset($data['action_items']);

            $data['created_by'] = $userId;
            $meeting = MeetingMinute::create($data);

            $this->storeFiles($meeting, $files);
            $this->syncActionItems($meeting, $actionItems);

            return $this->getOne($meeting->id);
        });
    }

    public function update(int $id, array $data, array $files = []): MeetingMinute
    {
        return DB::transaction(function () use ($id, $data, $files) {
            $meeting = MeetingMinute::findOrFail($id);

            $hasActionItems = array_key_exists('action_items', $data);
            $actionItems = $data['action_items'] ?? [];
            unset($data['action_items']);

            $meeting->update($data);

            if ($hasActionItems) {
                $meeting->actionItems()->delete();
                $this->syncActionItems($meeting, $actionItems);
            }

            $this->storeFiles($meeting, $files);

            return $this->getOne($meeting->id);
        });
    }

    public function delete(int $id): void
    {
        $meeting = MeetingMinute::with('files')->findOrFail($id);

        foreach ($meeting->files as $file) {
            Storage::disk('local')->delete($file->file_path);
        }

        $meeting->delete();
    }

    public function addFiles(int $id, array $files = []): MeetingMinute
    {
        $meeting = MeetingMinute::findOrFail($id);
        $this->storeFiles($meeting, $files);

        return $this->getOne($meeting->id);
    }

    public function deleteFile(int $fileId): void
    {
        $file = MeetingMinuteFile::findOrFail($fileId);
        Storage::disk('local')->delete($file->file_path);
        $file->delete();
    }

    public function fileDownload(int $fileId)
    {
        $file = MeetingMinuteFile::findOrFail($fileId);

        return Storage::disk('local')->download($file->file_path, $file->file_name);
    }

    private function storeFiles(MeetingMinute $meeting, array $files): void
    {
        foreach ($files as $file) {
            if (!$file) continue;
            $path = $file->store('meetings/files', 'local');
            $meeting->files()->create([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }

    private function syncActionItems(MeetingMinute $meeting, array $actionItems): void
    {
        foreach ($actionItems as $item) {
            if (empty($item['item'])) continue;
            MeetingActionItem::create([
                'meeting_minute_id' => $meeting->id,
                'item' => $item['item'],
                'assigned_to' => $item['assigned_to'] ?? null,
                'due_date' => $item['due_date'] ?? null,
                'status' => $item['status'] ?? 'open',
            ]);
        }
    }
}
