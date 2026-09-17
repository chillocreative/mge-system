<?php

namespace App\Http\Controllers\Api\ReportData;

use App\Http\Controllers\Concerns\NormalizesNullableColumns;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectTestController extends Controller
{
    use NormalizesNullableColumns;

    public function index(int $projectId): JsonResponse
    {
        return $this->success(ProjectTest::where('project_id', $projectId)->orderBy('sort_order')->orderBy('test_date')->get());
    }

    public function store(int $projectId, Request $request): JsonResponse
    {
        Project::findOrFail($projectId);
        $validated = $this->validatePayload($request, true);
        $validated['project_id'] = $projectId;
        $validated['created_by'] = $request->user()->id;
        $validated = $this->dropNullColumns($validated, ['sort_order']);

        return $this->created(ProjectTest::create($validated), 'Test added.');
    }

    public function update(int $projectId, int $testId, Request $request): JsonResponse
    {
        $test = ProjectTest::where('project_id', $projectId)->findOrFail($testId);
        $test->update($this->dropNullColumns($this->validatePayload($request, false), ['sort_order']));

        return $this->success($test->fresh(), 'Test updated.');
    }

    public function destroy(int $projectId, int $testId): JsonResponse
    {
        ProjectTest::where('project_id', $projectId)->findOrFail($testId)->delete();

        return $this->success(null, 'Test removed.');
    }

    private function validatePayload(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'ref_no' => ['nullable', 'string', 'max:100'],
            'name' => [$required, 'string', 'max:255'],
            'test_date' => ['nullable', 'date'],
            'result' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
