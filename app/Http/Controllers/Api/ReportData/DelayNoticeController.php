<?php

namespace App\Http\Controllers\Api\ReportData;

use App\Http\Controllers\Concerns\NormalizesNullableColumns;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectDelayNotice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DelayNoticeController extends Controller
{
    use NormalizesNullableColumns;

    public function index(int $projectId): JsonResponse
    {
        return $this->success(ProjectDelayNotice::where('project_id', $projectId)->orderBy('sort_order')->orderBy('submitted_date')->get());
    }

    public function store(int $projectId, Request $request): JsonResponse
    {
        Project::findOrFail($projectId);
        $validated = $this->validatePayload($request, true, $projectId);
        $validated['project_id'] = $projectId;
        $validated['created_by'] = $request->user()->id;
        $validated = $this->dropNullColumns($validated, ['status', 'sort_order']);

        return $this->created(ProjectDelayNotice::create($validated), 'Delay notice added.');
    }

    public function update(int $projectId, int $noticeId, Request $request): JsonResponse
    {
        $notice = ProjectDelayNotice::where('project_id', $projectId)->findOrFail($noticeId);
        $notice->update($this->dropNullColumns($this->validatePayload($request, false, $projectId), ['status', 'sort_order']));

        return $this->success($notice->fresh(), 'Delay notice updated.');
    }

    public function destroy(int $projectId, int $noticeId): JsonResponse
    {
        ProjectDelayNotice::where('project_id', $projectId)->findOrFail($noticeId)->delete();

        return $this->success(null, 'Delay notice removed.');
    }

    private function validatePayload(Request $request, bool $creating, int $projectId): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'issue' => ['nullable', 'string'],
            'correspondence_id' => ['nullable', 'integer', Rule::exists('project_correspondences', 'id')->where('project_id', $projectId)],
            'reg_number' => ['nullable', 'string', 'max:100'],
            'submitted_date' => [$required, 'date'],
            'submitted_via' => ['nullable', 'string', 'max:50'],
            'reply_date' => ['nullable', 'date', 'after_or_equal:submitted_date'],
            'status' => ['nullable', 'in:open,close'],
            'impact' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
