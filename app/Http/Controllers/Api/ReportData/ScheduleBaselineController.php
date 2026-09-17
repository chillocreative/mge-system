<?php

namespace App\Http\Controllers\Api\ReportData;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectScheduleBaseline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleBaselineController extends Controller
{
    public function index(int $projectId): JsonResponse
    {
        return $this->success(ProjectScheduleBaseline::where('project_id', $projectId)->orderBy('month')->get());
    }

    public function replace(int $projectId, Request $request): JsonResponse
    {
        Project::findOrFail($projectId);
        $validated = $request->validate([
            'rows' => ['present', 'array', 'max:120'],
            'rows.*.month' => ['required', 'date_format:Y-m', 'distinct'],
            'rows.*.scheduled_physical_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'rows.*.scheduled_financial_amount' => ['nullable', 'numeric', 'min:0'],
            'rows.*.scheduled_financial_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        DB::transaction(function () use ($projectId, $validated) {
            ProjectScheduleBaseline::where('project_id', $projectId)->delete();
            foreach ($validated['rows'] as $r) {
                ProjectScheduleBaseline::create([
                    'project_id' => $projectId,
                    'month' => $r['month'].'-01',
                    'scheduled_physical_pct' => $r['scheduled_physical_pct'],
                    'scheduled_financial_amount' => $r['scheduled_financial_amount'] ?? null,
                    'scheduled_financial_pct' => $r['scheduled_financial_pct'] ?? null,
                    'source' => 'manual',
                ]);
            }
        });

        return $this->index($projectId);
    }
}
