<?php

namespace App\Http\Controllers\Api\ReportData;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectResourceCategory;
use App\Services\ReportData\ResourceCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResourceCategoryController extends Controller
{
    public function __construct(
        private readonly ResourceCategoryService $categories,
    ) {}

    public function index(int $projectId, Request $request): JsonResponse
    {
        $kind = $request->validate(['kind' => ['required', 'in:worker,machinery']])['kind'];

        return $this->success([
            'rows' => ProjectResourceCategory::where('project_id', $projectId)->where('kind', $kind)->orderBy('sort_order')->get(),
            'effective' => $this->categories->namesFor($projectId, $kind),
        ]);
    }

    public function replace(int $projectId, Request $request): JsonResponse
    {
        Project::findOrFail($projectId);
        $request->merge(['kind' => $request->query('kind', $request->input('kind'))]);
        $validated = $request->validate([
            'kind' => ['required', 'in:worker,machinery'],
            'rows' => ['present', 'array', 'max:100'],
            'rows.*.group' => ['nullable', 'string', 'max:100'],
            'rows.*.name' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'rows.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'rows.*.active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($projectId, $validated) {
            ProjectResourceCategory::where('project_id', $projectId)->where('kind', $validated['kind'])->delete();
            foreach ($validated['rows'] as $i => $r) {
                ProjectResourceCategory::create([
                    'project_id' => $projectId, 'kind' => $validated['kind'], 'group' => $r['group'] ?? null,
                    'name' => $r['name'], 'sort_order' => $r['sort_order'] ?? $i, 'active' => $r['active'] ?? true,
                ]);
            }
        });

        return $this->index($projectId, $request);
    }

    // One name added from the site log form, so a new machinery type needs no trip to the category editor.
    public function add(int $projectId, Request $request): JsonResponse
    {
        Project::findOrFail($projectId);
        $validated = $request->validate([
            'kind' => ['required', 'in:worker,machinery'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        return $this->success([
            'name' => $this->categories->addName($projectId, $validated['kind'], $validated['name']),
            'effective' => $this->categories->namesFor($projectId, $validated['kind']),
        ], 'Added to the list.');
    }

    public function seedDefaults(int $projectId, Request $request): JsonResponse
    {
        Project::findOrFail($projectId);
        $kind = $request->validate(['kind' => ['required', 'in:worker,machinery']])['kind'];
        $this->categories->seedDefaults($projectId, $kind);

        return $this->index($projectId, $request);
    }
}
