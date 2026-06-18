<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function __construct(private LeaveService $leaveService) {}

    // ── Leave Requests ──

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['employee_id', 'status', 'leave_type_id']);
        $perPage = min($request->integer('per_page', 15), 100);
        return $this->success($this->leaveService->list($filters, $perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'half_day' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $attachment = $request->file('attachment');
        unset($validated['attachment']);

        $leave = $this->leaveService->apply($validated, $request->user()->id, $attachment);
        return $this->created($leave, 'Leave request submitted successfully.');
    }

    public function show(int $id): JsonResponse
    {
        return $this->success($this->leaveService->get($id));
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        return $this->success($this->leaveService->approve($id, $request->user()->id), 'Leave request approved.');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string'],
        ]);

        return $this->success(
            $this->leaveService->reject($id, $request->user()->id, $validated['rejection_reason'] ?? null),
            'Leave request rejected.'
        );
    }

    public function cancel(int $id): JsonResponse
    {
        return $this->success($this->leaveService->cancel($id), 'Leave request cancelled.');
    }

    public function balance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'year' => ['nullable', 'integer'],
        ]);

        $year = $validated['year'] ?? now()->year;
        return $this->success($this->leaveService->balanceFor($validated['employee_id'], $year));
    }

    public function downloadAttachment(int $id)
    {
        return $this->leaveService->downloadAttachment($id);
    }

    // ── Leave Types ──

    public function types(): JsonResponse
    {
        return $this->success($this->leaveService->leaveTypes());
    }

    public function storeType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20'],
            'default_days_per_year' => ['required', 'integer', 'min:0'],
            'is_paid' => ['nullable', 'boolean'],
            'requires_attachment' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return $this->created($this->leaveService->createType($validated), 'Leave type created successfully.');
    }

    public function updateType(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:20'],
            'default_days_per_year' => ['sometimes', 'integer', 'min:0'],
            'is_paid' => ['sometimes', 'boolean'],
            'requires_attachment' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return $this->success($this->leaveService->updateType($id, $validated), 'Leave type updated successfully.');
    }

    public function destroyType(int $id): JsonResponse
    {
        $this->leaveService->deleteType($id);
        return $this->success(null, 'Leave type deleted successfully.');
    }
}
