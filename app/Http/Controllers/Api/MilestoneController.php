<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MilestoneController extends Controller
{
    public function index(int $projectId, Request $request): JsonResponse
    {
        $milestones = Milestone::where('project_id', $projectId)
            ->with('creator:id,first_name,last_name')
            ->when($request->status, fn ($q, $s) => $q->byStatus($s))
            ->orderBy('sort_order')
            ->orderBy('due_date')
            ->get();

        return $this->success($milestones);
    }

    public function store(int $projectId, Request $request): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:pending,in_progress,completed,overdue'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['project_id'] = $project->id;
        $validated['created_by'] = $request->user()->id;

        $milestone = Milestone::create($validated);

        return $this->created($milestone->load('creator:id,first_name,last_name'), 'Milestone created.');
    }

    public function show(int $projectId, int $milestoneId): JsonResponse
    {
        $milestone = Milestone::where('project_id', $projectId)
            ->with('creator:id,first_name,last_name')
            ->findOrFail($milestoneId);

        return $this->success($milestone);
    }

    public function update(int $projectId, int $milestoneId, Request $request): JsonResponse
    {
        $milestone = Milestone::where('project_id', $projectId)->findOrFail($milestoneId);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'completed_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:pending,in_progress,completed,overdue'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if (isset($validated['status']) && $validated['status'] === 'completed' && !$milestone->completed_date) {
            $validated['completed_date'] = now()->toDateString();
            $validated['progress'] = 100;
        }

        $milestone->update($validated);

        return $this->success($milestone->fresh()->load('creator:id,first_name,last_name'), 'Milestone updated.');
    }

    public function destroy(int $projectId, int $milestoneId): JsonResponse
    {
        $milestone = Milestone::where('project_id', $projectId)->findOrFail($milestoneId);
        $milestone->delete();

        return $this->success(null, 'Milestone deleted.');
    }
}
