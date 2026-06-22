<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TrainingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingController extends Controller
{
    public function __construct(private TrainingService $trainingService) {}

    // ── Records ──

    public function records(Request $request): JsonResponse
    {
        $filters = $request->only(['employee_id', 'category', 'hrdf_claimable', 'status']);
        $perPage = min($request->integer('per_page', 15), 100);

        return $this->success($this->trainingService->recordsList($filters, $perPage));
    }

    public function storeRecord(Request $request): JsonResponse
    {
        $validated = $this->validateRecord($request, true);

        return $this->created(
            $this->trainingService->createRecord($validated, $request->user()->id),
            'Training record added.'
        );
    }

    public function updateRecord(Request $request, int $id): JsonResponse
    {
        $validated = $this->validateRecord($request, false);

        return $this->success($this->trainingService->updateRecord($id, $validated), 'Training record updated.');
    }

    public function destroyRecord(int $id): JsonResponse
    {
        $this->trainingService->deleteRecord($id);

        return $this->success(null, 'Training record deleted.');
    }

    private function validateRecord(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'employee_id' => [$req, 'exists:employees,id'],
            'title' => [$req, 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'training_date' => [$req, 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:training_date'],
            'duration_days' => ['nullable', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'hrdf_claimable' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:scheduled,completed'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    // ── Overview ──

    public function overview(): JsonResponse
    {
        return $this->success($this->trainingService->overview());
    }

    // ── Requests ──

    public function requests(Request $request): JsonResponse
    {
        $filters = $request->only(['employee_id', 'status']);
        $perPage = min($request->integer('per_page', 15), 100);

        return $this->success($this->trainingService->requestsList($filters, $perPage));
    }

    public function storeRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string'],
            'preferred_date' => ['nullable', 'date'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        return $this->created(
            $this->trainingService->createRequest($validated, $request->user()->id),
            'Training request submitted.'
        );
    }

    public function approveRequest(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['review_notes' => ['nullable', 'string']]);

        return $this->success(
            $this->trainingService->approveRequest($id, $request->user(), $validated['review_notes'] ?? null),
            'Training request approved.'
        );
    }

    public function rejectRequest(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['review_notes' => ['nullable', 'string']]);

        return $this->success(
            $this->trainingService->rejectRequest($id, $request->user(), $validated['review_notes'] ?? null),
            'Training request rejected.'
        );
    }

    // ── Self-service ──

    public function my(Request $request): JsonResponse
    {
        $employee = $this->trainingService->myEmployee($request->user()->id);

        if (!$employee) {
            return $this->success(['employee' => null, 'records' => [], 'requests' => []]);
        }

        return $this->success(array_merge(
            ['employee' => $employee],
            $this->trainingService->myData($employee->id),
        ));
    }
}
