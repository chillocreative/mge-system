<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectDiscussion;
use App\Services\DiscussionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscussionController extends Controller
{
    public function __construct(private DiscussionService $discussionService) {}

    public function index(Request $request): JsonResponse
    {
        $projectId = $request->integer('project_id') ?: null;

        if (! $projectId) {
            return $this->success([], 'Select a project to view discussions.');
        }

        $perPage = min($request->integer('per_page', 20), 100);

        return $this->success($this->discussionService->list($projectId, $perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'exists:project_discussions,id'],
        ]);

        $post = $this->discussionService->post(
            $validated['project_id'],
            $validated['body'],
            $request->user()->id,
            $validated['parent_id'] ?? null,
        );

        return $this->created($post, 'Posted successfully.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $post = ProjectDiscussion::findOrFail($id);

        abort_unless(
            $post->posted_by === $request->user()->id || $request->user()->can('projects.edit'),
            403
        );

        $this->discussionService->delete($post);

        return $this->success(null, 'Deleted successfully.');
    }
}
