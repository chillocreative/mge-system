<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\SiteLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteLogController extends Controller
{
    public function index(int $projectId, Request $request): JsonResponse
    {
        $logs = SiteLog::where('project_id', $projectId)
            ->with('logger:id,first_name,last_name')
            ->when($request->date_from && $request->date_to, fn ($q) => $q->forPeriod($request->date_from, $request->date_to))
            ->orderByDesc('log_date')
            ->paginate($request->integer('per_page', 15));

        return $this->success($logs);
    }

    public function store(int $projectId, Request $request): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        $validated = $request->validate([
            'log_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'weather' => ['nullable', 'in:sunny,cloudy,rainy,stormy,windy,other'],
            'workers_count' => ['nullable', 'integer', 'min:0'],
            'work_performed' => ['nullable', 'string'],
            'materials_used' => ['nullable', 'string'],
            'equipment_used' => ['nullable', 'string'],
            'safety_notes' => ['nullable', 'string'],
            'issues' => ['nullable', 'string'],
        ]);

        $validated['project_id'] = $project->id;
        $validated['logged_by'] = $request->user()->id;

        $log = SiteLog::create($validated);

        return $this->created($log->load('logger:id,first_name,last_name'), 'Site log created.');
    }

    public function show(int $projectId, int $logId): JsonResponse
    {
        $log = SiteLog::where('project_id', $projectId)
            ->with('logger:id,first_name,last_name')
            ->findOrFail($logId);

        return $this->success($log);
    }

    public function update(int $projectId, int $logId, Request $request): JsonResponse
    {
        $log = SiteLog::where('project_id', $projectId)->findOrFail($logId);

        $validated = $request->validate([
            'log_date' => ['sometimes', 'date'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'weather' => ['nullable', 'in:sunny,cloudy,rainy,stormy,windy,other'],
            'workers_count' => ['nullable', 'integer', 'min:0'],
            'work_performed' => ['nullable', 'string'],
            'materials_used' => ['nullable', 'string'],
            'equipment_used' => ['nullable', 'string'],
            'safety_notes' => ['nullable', 'string'],
            'issues' => ['nullable', 'string'],
        ]);

        $log->update($validated);

        return $this->success($log->fresh()->load('logger:id,first_name,last_name'), 'Site log updated.');
    }

    public function destroy(int $projectId, int $logId): JsonResponse
    {
        $log = SiteLog::where('project_id', $projectId)->findOrFail($logId);
        $log->delete();

        return $this->success(null, 'Site log deleted.');
    }
}
