<?php

namespace App\Http\Controllers\Api\ReportData;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrgChartController extends Controller
{
    public function show(int $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);
        $rows = $project->members()->whereNull('project_members.left_at')->get()->map(fn ($u) => [
            'user_id' => $u->id,
            'name' => trim($u->first_name.' '.$u->last_name),
            'designation' => $u->pivot->designation,
            'reports_to_user_id' => $u->pivot->reports_to_user_id,
            'org_sort' => (int) $u->pivot->org_sort,
        ])->sortBy('org_sort')->values();

        return $this->success($this->tree($rows, null));
    }

    public function update(int $projectId, Request $request): JsonResponse
    {
        $project = Project::findOrFail($projectId);
        $memberIds = $project->members()->pluck('users.id')->all();

        $validated = $request->validate([
            'members' => ['required', 'array'],
            'members.*.user_id' => ['required', 'integer', 'in:'.implode(',', $memberIds ?: [0])],
            'members.*.designation' => ['nullable', 'string', 'max:255'],
            'members.*.reports_to_user_id' => ['nullable', 'integer', 'different:members.*.user_id', 'in:'.implode(',', $memberIds ?: [0])],
            'members.*.org_sort' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($project, $validated) {
            foreach ($validated['members'] as $m) {
                $project->members()->updateExistingPivot($m['user_id'], [
                    'designation' => $m['designation'] ?? null,
                    'reports_to_user_id' => $m['reports_to_user_id'] ?? null,
                    'org_sort' => $m['org_sort'] ?? 0,
                ]);
            }
        });

        return $this->show($projectId);
    }

    private function tree($rows, ?int $parentId): array
    {
        return $rows->where('reports_to_user_id', $parentId)->map(fn ($r) => $r + ['children' => $this->tree($rows, $r['user_id'])])->values()->all();
    }
}
