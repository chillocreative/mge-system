<?php

namespace App\Http\Controllers\Api\ReportData;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\ProjectProgressPeriod;
use App\Services\ReportData\ProgressService;
use App\Support\ReportPeriod;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProgressPeriodController extends Controller
{
    public function __construct(private ProgressService $progress) {}

    public function index(int $projectId): JsonResponse
    {
        return $this->success(ProjectProgressPeriod::where('project_id', $projectId)->orderBy('period_no')->get());
    }

    public function suggest(int $projectId, Request $request): JsonResponse
    {
        $project = Project::findOrFail($projectId);
        $request->validate(['period_end' => ['required', 'date']]);
        $end = Carbon::parse($request->period_end);
        $period = ReportPeriod::containing((int) $project->report_cutoff_day, $end);
        $baseline = $this->progress->scheduledFor($projectId, $period['end']);
        $contract = ProjectContract::where('project_id', $projectId)->where('is_main', true)->first();
        $fin = $this->progress->financialActual($projectId, $period['end'], $contract?->contract_sum !== null ? (float) $contract->contract_sum : null);
        $last = ProjectProgressPeriod::where('project_id', $projectId)->max('period_no');

        return $this->success([
            'period_no' => (int) $last + 1,
            'period_start' => $period['start']->toDateString(),
            'period_end' => $period['end']->toDateString(),
            'physical_scheduled_pct' => $baseline?->scheduled_physical_pct !== null ? (float) $baseline->scheduled_physical_pct : null,
            'financial_scheduled_pct' => $baseline?->scheduled_financial_pct !== null ? (float) $baseline->scheduled_financial_pct : null,
            'financial_actual_amount' => $fin['amount'],
            'financial_actual_pct' => $fin['pct'],
            'planning_days_completion' => $contract?->possession_date && $contract?->completion_date
                ? Carbon::parse($contract->possession_date)->diffInDays(Carbon::parse($contract->completion_date)) : null,
        ]);
    }

    public function store(int $projectId, Request $request): JsonResponse
    {
        Project::findOrFail($projectId);
        $validated = $this->validatePayload($request, $projectId, null);
        $validated = $this->progress->compute($validated);
        unset($validated['physical_variance'], $validated['financial_variance']);
        $validated['project_id'] = $projectId;
        $validated['created_by'] = $request->user()->id;

        return $this->created(ProjectProgressPeriod::create($validated), 'Progress period saved.');
    }

    public function update(int $projectId, int $periodId, Request $request): JsonResponse
    {
        $period = ProjectProgressPeriod::where('project_id', $projectId)->findOrFail($periodId);
        $validated = $this->validatePayload($request, $projectId, $periodId);
        $merged = $this->progress->compute(array_merge($period->toArray(), $validated));
        unset($merged['physical_variance'], $merged['financial_variance']);
        $period->update($merged);

        return $this->success($period->fresh(), 'Progress period updated.');
    }

    public function destroy(int $projectId, int $periodId): JsonResponse
    {
        ProjectProgressPeriod::where('project_id', $projectId)->findOrFail($periodId)->delete();

        return $this->success(null, 'Progress period deleted.');
    }

    private function validatePayload(Request $request, int $projectId, ?int $ignoreId): array
    {
        $required = $ignoreId ? 'sometimes' : 'required';

        return $request->validate([
            'period_no' => [$required, 'integer', 'min:1', Rule::unique('project_progress_periods')->where('project_id', $projectId)->ignore($ignoreId)],
            'period_start' => [$required, 'date'],
            'period_end' => [$required, 'date', 'after:period_start'],
            'planning_days_completion' => ['nullable', 'integer', 'min:0'],
            'physical_scheduled_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'physical_actual_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'financial_scheduled_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'financial_actual_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'financial_actual_amount' => ['nullable', 'numeric', 'min:0'],
            'ahead_delay_days' => ['nullable', 'integer'],
            'physical_status' => ['nullable', 'in:ON TRACK,AHEAD,DELAY'],
            'financial_status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
