<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Leave\LeaveEngine;
use App\Services\Leave\LeaveFiling;
use App\Services\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeaveController extends Controller
{
    public function __construct(
        private LeaveService $leaveService,
        private LeaveEngine $engine,
        private LeaveFiling $filing,
    ) {}

    // ── Leave Requests ──

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['employee_id', 'status', 'leave_type_id']);

        if ($request->boolean('filed_by_me')) {
            // What this user filed (a Site Clerk's filings for site staff).
            $filters = array_merge(Arr::only($filters, ['status', 'leave_type_id']), ['created_by' => $request->user()->id]);
        } elseif (! $request->user()->can('leave.manage')) {
            // Restrict non-HR users to their own employee record only
            $filters['employee_id'] = Employee::where('user_id', $request->user()->id)->value('id') ?: 0;
        }

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
            'attachment' => ['nullable', 'file', 'extensions:pdf,jpg,jpeg,png', 'max:1048576'],
            // An approval the head/PM gave by email, WhatsApp or a signed form, recorded by the Site Clerk or HR.
            'approved_offline' => ['nullable', 'boolean'],
            'offline_approver_name' => [Rule::requiredIf(fn () => $request->boolean('approved_offline')), 'nullable', 'string', 'max:255'],
            'offline_approval_note' => ['nullable', 'string', 'max:255'],
            'approval_proof' => [Rule::requiredIf(fn () => $request->boolean('approved_offline')), 'nullable', 'file', 'extensions:pdf,jpg,jpeg,png', 'max:20480'],
            'notice_override_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $filedFor = Employee::findOrFail($validated['employee_id']);

        abort_unless($this->filing->canFileFor($user, $filedFor), 403, 'You cannot file leave for this employee.');

        $offline = $request->boolean('approved_offline');
        if ($offline) {
            abort_unless($this->filing->canRecordOfflineApproval($user, $filedFor), 403, 'Only HR or the Site Clerk can record an approval received outside the system.');
        } else {
            unset($validated['offline_approver_name'], $validated['offline_approval_note']);
        }
        $validated['approved_offline'] = $offline;

        $this->enforceNotice($user, $validated);

        // Maternity leave is only available to female staff.
        $type = LeaveType::find($validated['leave_type_id']);
        if ($type && preg_match('/maternity/i', $type->name)) {
            $employee = Employee::find($validated['employee_id']);
            if (! $employee || $employee->gender !== 'female') {
                return $this->error('Maternity leave is only available to female staff.', 422);
            }
        }

        $attachment = $request->file('attachment');
        $approvalProof = $request->file('approval_proof');
        unset($validated['attachment'], $validated['approval_proof']);

        $leave = $this->leaveService->apply($validated, $user->id, $attachment, $approvalProof);

        return $this->created($leave, 'Leave request submitted successfully.');
    }

    /**
     * Preview what a leave request would cost, without creating it.
     *
     * Plan 27.6b: two employees requesting the same dates can be deducted
     * different amounts, because office staff rest Sat+Sun while site crews rest
     * only Sunday. That is correct, but it will read as a bug to the person
     * looking at it — so the apply form shows the working:
     *
     *     12 Sep – 16 Sep (5 calendar days)
     *     Rest day: 14 Sep (Sunday)
     *     Public holiday: none
     *     Deducted from balance: 4.0 days
     *
     * Showing the breakdown up front settles the "why is his 4 and mine 5?"
     * question before it becomes a ticket for HR.
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'half_day' => ['nullable', 'boolean'],
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $leaveType = LeaveType::findOrFail($validated['leave_type_id']);

        // Only for someone the user may file leave for.
        if (! $this->filing->canFileFor($request->user(), $employee)) {
            return $this->forbidden('You can only preview leave you may file.');
        }

        if (! $this->engine->enabled()) {
            return $this->success(['engine_enabled' => false]);
        }

        $calculated = $this->engine->calculate(
            $employee,
            $validated['start_date'],
            $validated['end_date'],
            (bool) ($validated['half_day'] ?? false),
        );

        $byYear = $calculated->deductedByYear();

        return $this->success([
            'engine_enabled' => true,
            'calendar_days' => $calculated->calendarDays(),
            'deducted_days' => $calculated->totalDeducted(),
            'deducted_by_year' => $byYear,
            'shortfall_days' => $this->engine->shortfall($employee, $leaveType, $calculated),
            'spans_multiple_years' => $calculated->spansMultipleYears(),
            'exclusions' => $calculated->exclusions(),
            'days' => $calculated->days,
            'balances' => collect(array_keys($byYear) ?: [(int) date('Y', strtotime($validated['start_date']))])
                ->mapWithKeys(fn (int $year) => [
                    $year => $this->engine->summary($employee, $leaveType, $year),
                ]),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        // HR (records), the employee themself, or whoever can decide the request.
        return $this->success($this->leaveService->getForViewer($request->user(), $id));
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $leave = LeaveRequest::with('employee')->findOrFail($id);
        $actor = $request->user();

        // Prevent self-approval
        if ($leave->employee->user_id === $actor->id) {
            return $this->forbidden('You cannot approve your own leave request.');
        }

        return $this->success($this->leaveService->approve($id, $actor), 'Leave request approved.');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $leave = LeaveRequest::with('employee')->findOrFail($id);
        $actor = $request->user();

        // Prevent self-rejection
        if ($leave->employee->user_id === $actor->id) {
            return $this->forbidden('You cannot reject your own leave request.');
        }

        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string'],
        ]);

        return $this->success(
            $this->leaveService->reject($id, $actor, $validated['rejection_reason'] ?? null),
            'Leave request rejected.'
        );
    }

    /**
     * Requests awaiting the current user's decision: their own department's staff (or the
     * Directors' share). HR is informed about leave but has no approvals queue.
     */
    public function pendingApprovals(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);

        return $this->success($this->leaveService->pendingApprovalsFor(
            $request->user(),
            $perPage,
            max($request->integer('page', 1), 1),
        ));
    }

    /**
     * Employees the current user may file leave for (themselves; HR: anyone; Site Clerk: their
     * department's site staff), and what else they may do on the form.
     */
    public function fileableEmployees(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'employees' => $this->filing->fileableEmployees($user)->map(fn (Employee $e) => [
                'id' => $e->id,
                'employee_no' => $e->employee_no,
                'name' => $e->full_name,
                'category' => $e->category,
                'is_self' => (int) $e->user_id === (int) $user->id,
            ])->values(),
            'can_override_notice' => $user->can('leave.manage'),
            'can_record_offline_approval' => $user->can('leave.manage') || $this->filing->isSiteClerk($user),
            'notice_days' => config('leave.notice_days'),
        ]);
    }

    /**
     * Approved leave waiting for HR to record.
     */
    public function toRecord(Request $request): JsonResponse
    {
        return $this->success($this->leaveService->toRecord(min($request->integer('per_page', 15), 100)));
    }

    /**
     * HR records an approved request; unpaid = none | all | excess when the balance does not cover it.
     */
    public function record(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['unpaid' => ['nullable', 'in:none,all,excess']]);

        return $this->success(
            $this->leaveService->record($id, $request->user(), $validated['unpaid'] ?? 'none'),
            'Leave recorded.'
        );
    }

    /**
     * Annual leave is applied for at least N days before it starts (config leave.notice_days).
     * HR may file an exception, but must say why.
     */
    private function enforceNotice($user, array &$validated): void
    {
        $type = LeaveType::find($validated['leave_type_id']);
        $noticeDays = $type ? (config('leave.notice_days')[$type->code] ?? null) : null;

        if (! $noticeDays || ! Carbon::parse($validated['start_date'])->lt(Carbon::today()->addDays($noticeDays))) {
            unset($validated['notice_override_reason']);

            return;
        }

        $field = 'start_date';

        if (! $user->can('leave.manage')) {
            throw ValidationException::withMessages([$field => ["{$type->name} must be applied for at least {$noticeDays} days before it starts."]]);
        }

        if (empty($validated['notice_override_reason'])) {
            throw ValidationException::withMessages(['notice_override_reason' => ["This is less than {$noticeDays} days' notice. State the reason for the exception."]]);
        }
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

    public function cancel(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if ($user->can('leave.manage')) {
            return $this->success($this->leaveService->cancel($id), 'Leave request cancelled.');
        }

        $employeeId = Employee::where('user_id', $user->id)->value('id');
        $leave = LeaveRequest::where('id', $id)->where('employee_id', $employeeId)->firstOrFail();

        // Only allow cancellation of own requests in pending/approved states
        if (! in_array($leave->status, ['pending', 'approved'], true)) {
            return $this->forbidden('You can only cancel your own pending or approved leave requests.');
        }

        return $this->success($this->leaveService->cancel($id), 'Leave request cancelled.');
    }

    public function balance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'year' => ['nullable', 'integer'],
        ]);

        // Restrict non-HR users to their own employee balance
        if (! $request->user()->can('leave.manage')) {
            $validated['employee_id'] = Employee::where('user_id', $request->user()->id)->value('id') ?: 0;
        }

        $year = $validated['year'] ?? now()->year;

        return $this->success($this->leaveService->balanceFor($validated['employee_id'], $year));
    }

    public function downloadAttachment(Request $request, int $id)
    {
        // Same visibility as the request itself (404 for anyone who may not see it).
        $this->leaveService->getForViewer($request->user(), $id);

        return $this->leaveService->downloadAttachment($id);
    }

    /**
     * The proof of an approval received outside the system; same visibility as the request.
     */
    public function downloadApprovalProof(Request $request, int $id)
    {
        $this->leaveService->getForViewer($request->user(), $id);

        return $this->leaveService->downloadApprovalProof($id);
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

        if ($request->boolean('requires_director_approval') && ! $request->filled('director_approver_id')) {
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

        if ($request->boolean('requires_director_approval') && ! $request->filled('director_approver_id')) {
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
