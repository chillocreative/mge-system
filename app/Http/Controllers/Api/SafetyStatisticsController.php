<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SafetyManHour;
use App\Services\Safety\SafetyStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SafetyStatisticsController extends Controller
{
    public function __construct(private readonly SafetyStatisticsService $stats) {}

    public function index(Request $request): JsonResponse
    {
        $year = $request->integer('year') ?: (int) now()->year;
        $projectId = $request->filled('project_id') ? $request->integer('project_id') : null;

        return $this->success($this->stats->forYear($year, $projectId));
    }

    public function manHoursIndex(Request $request): JsonResponse
    {
        $query = SafetyManHour::with('project:id,name,code')->orderByDesc('year')->orderByDesc('month');

        if ($request->filled('year')) {
            $query->where('year', $request->integer('year'));
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        return $this->success($query->get());
    }

    /**
     * Record (or overwrite) the man-hours for a project-month. Upsert on the
     * unique key so re-submitting a corrected figure replaces rather than
     * stacks — matching how the figure is actually maintained month to month.
     */
    public function manHoursStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'exists:projects,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'man_hours' => ['required', 'integer', 'min:0'],
        ]);

        $record = SafetyManHour::updateOrCreate(
            ['project_id' => $data['project_id'] ?? null, 'year' => $data['year'], 'month' => $data['month']],
            ['man_hours' => $data['man_hours'], 'recorded_by' => $request->user()->id],
        );

        return $this->success($record->load('project:id,name,code'), 'Man-hours recorded.');
    }

    public function manHoursDestroy(int $id): JsonResponse
    {
        SafetyManHour::findOrFail($id)->delete();

        return $this->success(null, 'Man-hours entry removed.');
    }
}
