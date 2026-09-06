<?php

namespace App\Services;

use App\Models\Memo;
use App\Models\MemoAttachment;
use App\Models\MemoRecipient;
use App\Models\Project;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MemoService
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * @param  array<\Illuminate\Http\UploadedFile>  $files
     */
    public function send(array $data, User $sender, array $files = []): Memo
    {
        return DB::transaction(function () use ($data, $sender, $files) {
            $recipientIds = $this->resolveRecipients($data, $sender);

            $memo = Memo::create([
                'from_user_id' => $sender->id,
                'title' => $data['title'],
                'body' => $data['body'],
                'audience' => $data['audience'],
                'project_id' => $data['audience'] === 'project_members' ? ($data['project_id'] ?? null) : null,
                'sent_at' => now(),
            ]);

            foreach ($files as $file) {
                $path = $file->store('memos/attachments', 'local');
                MemoAttachment::create([
                    'memo_id' => $memo->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getClientMimeType(),
                ]);
            }

            $rows = collect($recipientIds)->map(fn ($uid) => [
                'memo_id' => $memo->id,
                'user_id' => $uid,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            if ($rows) {
                MemoRecipient::insert($rows);
            }

            $this->notifications->notifyUserIds(
                $recipientIds,
                'New memo: '.$memo->title,
                \Illuminate\Support\Str::limit(strip_tags($memo->body), 120),
                'memo',
                '/hr/memos',
                ['memo_id' => $memo->id],
            );

            return $memo->load('sender:id,first_name,last_name', 'project:id,name', 'attachments');
        });
    }

    /**
     * @return array<int> recipient user ids (sender excluded)
     */
    private function resolveRecipients(array $data, User $sender): array
    {
        $ids = match ($data['audience']) {
            'all_users' => User::where('status', 'active')->pluck('id')->all(),
            'selected_users' => $data['user_ids'] ?? [],
            'project_members' => $this->projectAudience((int) ($data['project_id'] ?? 0)),
            default => [],
        };

        return collect($ids)->map(fn ($id) => (int) $id)->unique()
            ->reject(fn ($id) => $id === $sender->id)->values()->all();
    }

    private function projectAudience(int $projectId): array
    {
        $project = Project::with('members:id')->find($projectId);
        if (! $project) {
            return [];
        }

        $ids = $project->members->pluck('id')->all();
        if ($project->manager_id) {
            $ids[] = $project->manager_id;
        }

        return $ids;
    }

    public function getInbox(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return Memo::with([
            'sender:id,first_name,last_name',
            'project:id,name',
            'recipients' => fn ($q) => $q->where('user_id', $userId),
        ])
            ->whereHas('recipients', fn ($q) => $q->where('user_id', $userId))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getSent(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return Memo::with(['sender:id,first_name,last_name', 'project:id,name'])
            ->withCount('recipients')
            ->where('from_user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function get(int $memoId, int $userId): Memo
    {
        $memo = Memo::with(['sender:id,first_name,last_name', 'project:id,name', 'attachments'])
            ->withCount('recipients')
            ->findOrFail($memoId);

        $isRecipient = $memo->recipients()->where('user_id', $userId)->exists();
        abort_unless($isRecipient || $memo->from_user_id === $userId, 403, 'You do not have access to this memo.');

        if ($isRecipient) {
            $this->markAsRead($memoId, $userId);
        }

        return $memo;
    }

    public function markAsRead(int $memoId, int $userId): void
    {
        MemoRecipient::where('memo_id', $memoId)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function unreadCount(int $userId): int
    {
        return MemoRecipient::where('user_id', $userId)->whereNull('read_at')->count();
    }
}
