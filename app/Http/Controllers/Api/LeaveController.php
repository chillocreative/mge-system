<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
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

        // Maternity leave is only available to female staff.
        $type = LeaveType::find($validated['leave_type_id']);
        if ($type && preg_match('/maternity/i', $type->name)) {
            $employee = Employee::find($validated['employee_id']);
            if (!$employee || $employee->gender !== 'female') {
                return $this->error('Maternity leave is only available to female staff.', 422);
            }
        }

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
        return $this->success($this->leaveService->approve($id, $request->user()), 'Leave request approved.');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string'],
        ]);

        return $this->success(
            $this->leaveService->reject($id, $request->user(), $validated['rejection_reason'] ?? null),
            'Leave request rejected.'
        );
    }

    /**
     * Requests awaiting the current user's approval. Admins (leave.manage) see all pending.
     */
    public function pendingApprovals(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min($request->integer('per_page', 15), 100);

        $query = LeaveRequest::with([
            'employee:id,employee_no,first_name,last_name',
            'leaveType:id,name,code,requires_director_approval,manager_approver_id,director_approver_id',
            'managerApprover:id,first_name,last_name',
        ])->where('status', 'pending')->orderByDesc('created_at');

        if (!$user->can('leave.manage')) {
            // Designated per-type approvers see their own; system-wide Manager/Director
            // approvers see every request awaiting their stage.
            $query->where(function ($q) use ($user) {
                $q->awaitingApprovalBy($user->id);
                if ($user->is_manager) {
                    $q->orWhere('current_approval_level', 'manager');
                }
                if ($user->is_director) {
                    $q->orWhere('current_approval_level', 'director');
                }
            });
        }

        return $this->success($query->paginate($perPage));
    }

    /**
     * The employee record linked to the current user (for self-service submission).
     */
    public function myEmployee(Request $request): JsonResponse
    {
        $employee = Employee::with('designation:id,name')
            ->where('user_id', $request->user()->id)
            ->first();

        return $this->success($employee);
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
            'requires_director_approval' => ['nullable', 'boolean'],
            'manager_approver_id' => ['nullable', 'exists:users,id'],
            'director_approver_id' => ['nullable', 'exists:users,id'],
        ]);

        if ($request->boolean('requires_director_approval') && !$request->filled('director_approver_id')) {
            return $this->error('A director approver is required when director approval is enabled.', 422);
        }

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
            'requires_director_approval' => ['sometimes', 'boolean'],
            'manager_approver_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'director_approver_id' => ['sometimes', 'nullable', 'exists:users,id'],
        ]);

        if ($request->boolean('requires_director_approval') && !$request->filled('director_approver_id')) {
            return $this->error('A director approver is required when director approval is enabled.', 422);
        }

        return $this->success($this->leaveService->updateType($id, $validated), 'Leave type updated successfully.');
    }

    public function destroyType(int $id): JsonResponse
    {
        $this->leaveService->deleteType($id);
        return $this->success(null, 'Leave type deleted successfully.');
    }
}
