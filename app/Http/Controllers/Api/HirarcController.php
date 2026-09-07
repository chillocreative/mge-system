<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AssertsSiteInProject;
use App\Http\Controllers\Controller;
use App\Models\HirarcAssessment;
use App\Services\Safety\RiskMatrix;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HirarcController extends Controller
{
    use AssertsSiteInProject;

    public function index(Request $request): JsonResponse
    {
        $query = HirarcAssessment::with(['project:id,name,code', 'site:id,name', 'preparer:id,first_name,last_name'])
            ->withCount('items')
            ->orderByDesc('created_at');

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return $this->success($query->paginate(min($request->integer('per_page', 15), 100)));
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(
            HirarcAssessment::with(['items', 'project:id,name,code', 'site:id,name', 'preparer:id,first_name,last_name'])->findOrFail($id),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);
        $this->assertSiteInProject($data['site_id'] ?? null, $data['project_id'] ?? null);
        $items = $data['items'] ?? [];
        unset($data['items']);

        $assessment = DB::transaction(function () use ($data, $items, $request) {
            $data['prepared_by'] = $request->user()->id;
            $assessment = HirarcAssessment::create($data);
            $this->syncItems($assessment, $items);

            return $assessment;
        });

        return $this->created($assessment->load('items'), 'HIRARC created.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $assessment = HirarcAssessment::findOrFail($id);
        $data = $this->validatePayload($request, true);
        $this->assertSiteInProject($data['site_id'] ?? null, $data['project_id'] ?? $assessment->project_id);
        $items = $data['items'] ?? null;
        unset($data['items']);

        DB::transaction(function () use ($assessment, $data, $items) {
            $assessment->update($data);
            if ($items !== null) {
                $this->syncItems($assessment, $items);
            }
        });

        return $this->success($assessment->fresh()->load('items'), 'HIRARC updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        // Never hard-delete a safety record (plan 26.3) — archive it.
        HirarcAssessment::findOrFail($id)->update(['status' => 'archived']);

        return $this->success(null, 'HIRARC archived.');
    }

    /**
     * Replace the assessment's items, computing each row's rating and level from
     * the matrix so the stored values can never disagree with likelihood ×
     * severity.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(HirarcAssessment $assessment, array $items): void
    {
        $assessment->items()->delete();

        foreach (array_values($items) as $i => $item) {
            $likelihood = (int) ($item['likelihood'] ?? 1);
            $severity = (int) ($item['severity'] ?? 1);
            $rating = RiskMatrix::rating($likelihood, $severity);

            $assessment->items()->create([
                'hazard' => $item['hazard'],
                'risk' => $item['risk'] ?? null,
                'existing_control' => $item['existing_control'] ?? null,
                'likelihood' => max(1, min(5, $likelihood)),
                'severity' => max(1, min(5, $severity)),
                'risk_rating' => $rating,
                'risk_level' => RiskMatrix::level($rating),
                'recommended_control' => $item['recommended_control'] ?? null,
                'pic' => $item['pic'] ?? null,
                'due_date' => $item['due_date'] ?? null,
                'sort_order' => $i,
            ]);
        }
    }

    private function validatePayload(Request $request, bool $partial = false): array
    {
        $rule = fn (array $r) => $partial ? array_merge(['sometimes'], $r) : $r;

        return $request->validate([
            'title' => $rule(['required', 'string', 'max:255']),
            'process' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'site_id' => ['nullable', 'exists:project_sites,id'],
            'assessment_date' => ['nullable', 'date'],
            'review_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:active,archived'],
            'items' => ['nullable', 'array'],
            'items.*.hazard' => ['required_with:items', 'string'],
            'items.*.risk' => ['nullable', 'string'],
            'items.*.existing_control' => ['nullable', 'string'],
            'items.*.likelihood' => ['nullable', 'integer', 'between:1,5'],
            'items.*.severity' => ['nullable', 'integer', 'between:1,5'],
            'items.*.recommended_control' => ['nullable', 'string'],
            'items.*.pic' => ['nullable', 'string', 'max:255'],
            'items.*.due_date' => ['nullable', 'date'],
        ]);
    }
}
