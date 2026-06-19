<?php

namespace App\Services;

use App\Models\ProjectDiscussion;
use Illuminate\Pagination\LengthAwarePaginator;

class DiscussionService
{
    public function list(int $projectId, int $perPage = 20): LengthAwarePaginator
    {
        return ProjectDiscussion::forProject($projectId)
            ->topLevel()
            ->with([
                'author:id,first_name,last_name',
                'replies' => fn ($q) => $q->orderBy('created_at')->with('author:id,first_name,last_name'),
            ])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function post(int $projectId, string $body, int $userId, ?int $parentId = null): ProjectDiscussion
    {
        $discussion = ProjectDiscussion::create([
            'project_id' => $projectId,
            'parent_id' => $parentId,
            'body' => $body,
            'posted_by' => $userId,
        ]);

        return $discussion->load('author:id,first_name,last_name');
    }

    public function delete(ProjectDiscussion $post): void
    {
        $post->delete();
    }
}
