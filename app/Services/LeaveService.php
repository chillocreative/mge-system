<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveDay;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\LeaveStatusNotification;
use App\Services\Leave\LeaveEngine;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LeaveService
{
    public function __construct(private readonly LeaveEngine $engine) {}

    // ── Leave Requests ──

    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = LeaveRequest::with([
            'employee:id,employee_no,first_name,last_name',
            'leaveType:id,name,code,is_paid',
            'approver:id,first_name,last_name',
        ])->orderByDesc('created_at');

        if (! empty($filters['employee_id'])) {
            $query->forEmployee($filters['employee_id']);
        }
        if (! empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }
        if (! empty($filters['leave_type_id'])) {
            $query->where('leave_type_id', $filters['leave_type_id']);
        }

        return $query->paginate($perPage);
    }

    public function get(int $id): LeaveRequest
    {
        return LeaveRequest::with([
            'employee:id,employee_no,first_name,last_name',
            'leaveType:id,name,code,is_paid,requires_attachment',
            'approver:id,first_name,last_name',
            'creator:id,first_name,last_name',
        ])->findOrFail($id);
    }

    public function apply(array $data, int $userId, $attachment = null): LeaveRequest
    {
        return DB::transaction(function () use ($data, $userId, $attachment) {
            $start = Carbon::parse($data['start_date']);
            $end = Carbon::parse($data['end_date']);
            $halfDay = ! empty($data['half_day']);

            $employee = Employee::findOrFail($data['employee_id']);
            $leaveType = LeaveType::findOrFail($data['leave_type_id']);

            $calculated = null;

            if ($this->engine->enabled()) {
                // Supporting document requirement — declared on leave_types but
                // never enforced before now.
                if ($leaveType->requires_attachment && ! $attachment) {
                    abort(422, "A supporting document is required for {$leaveType->name}.");
                }

                $calculated = $this->engine->calculate($employee, $start, $end, $halfDay);
                $days = $calculated->totalDeducted();

                $this->engine->assertWithinBalance($employee, $leaveType, $calculated);
            } else {
                $days = $halfDay ? 0.5 : ($start->diffInDays($end) + 1);
            }

            if ($attachment) {
                $data['attachment_path'] = $attachment->store('leave/attachments', 'local');
            }

            unset($data['half_day']);

            $data['days_count'] = $days;
            $data['status'] = 'pending';
            $data['current_approval_level'] = 'manager';
            $data['created_by'] = $userId;

            $request = LeaveRequest::create($data);

            if ($calculated !== null) {
                $this->engine->persistDays($request, $calculated);
            }

            $request->load([
                'employee:id,employee_no,first_name,last_name,user_id',
                'leaveType:id,name,code,is_paid,requires_director_approval,manager_approver_id,director_approver_id',
            ]);

            $this->notifyManager($request);

            return $request;
        });
    }

    public function approve(int $id, User $actor): LeaveRequest
    {
        return DB::transaction(function () use ($id, $actor) {
            $request = LeaveRequest::with([
                'leaveType',
                'employee:id,employee_no,first_name,last_name,user_id',
            ])->findOrFail($id);

            abort_if($request->status !== 'pending', 422, 'This request is no longer pending approval.');

            $level = $request->current_approval_level ?: 'manager';
            $this->authorizeStage($request, $actor, $level);

            if ($level === 'manager') {
                $request->manager_approved_by = $actor->id;
                $request->manager_approved_at = now();

                if ($request->leaveType->requires_director_approval && $request->leaveType->director_approver_id) {
                    $request->current_approval_level = 'director';
                    $request->save();
                    $this->notifyDirector($request);

                    return $this->loadFull($request);
                }

                $this->finalizeApproval($request, $actor);

                return $this->loadFull($request);
            }

            // Director stage
            $request->director_approved_by = $actor->id;
            $request->director_approved_at = now();
            $this->finalizeApproval($request, $actor);

            return $this->loadFull($request);
        });
    }

    public function reject(int $id, User $actor, ?string $reason = null): LeaveRequest
    {
        $request = LeaveRequest::with([
            'leaveType',
            'employee:id,employee_no,first_name,last_name,user_id',
        ])->findOrFail($id);

        abort_if($request->status !== 'pending', 422, 'This request is no longer pending approval.');

        $level = $request->current_approval_level ?: 'manager';
        $this->authorizeStage($request, $actor, $level);

        $request->update([
            'status' => 'rejected',
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        if ($this->engine->enabled()) {
            // A rejected request must stop consuming the pending balance.
            $this->engine->releaseDays($request);
            $this->syncBalancesFor($request, [
                Carbon::parse($request->start_date)->year,
                Carbon::parse($request->end_date)->year,
            ]);
        }

        $this->notifyEmployee($request, 'rejected');

        return $this->loadFull($request);
    }

    /**
     * Finalize a request as fully approved and deduct the leave balance.
     */
    private function finalizeApproval(LeaveRequest $request, User $actor): void
    {
        $request->status = 'approved';
        $request->approved_by = $actor->id;
        $request->approved_at = now();
        $request->rejection_reason = null;
        $request->save();

        if ($this->engine->enabled()) {
            // Balances are derived from leave_days rather than incremented, so a
            // request spanning New Year lands on the correct year's balance
            // instead of being charged entirely to its start date.
            $this->syncBalancesFor($request);
        } else {
            $year = Carbon::parse($request->start_date)->year;
            $balance = $this->ensureBalance($request->employee_id, $request->leave_type_id, $year);

            $balance->used_days = (float) $balance->used_days + (float) $request->days_count;
            $balance->remaining_days = (float) $balance->entitled_days - (float) $balance->used_days;
            $balance->save();
        }

        $this->notifyEmployee($request, 'approved');
    }

    /**
     * Recalculate and store the balance snapshot for every leave year this
     * request touches.
     *
     * A request from 30 Dec to 3 Jan draws on two different years' balances, so
     * updating only the start date's year would leave the other one wrong.
     *
     * @param  array<int, int>|null  $years  Defaults to the years the request's leave_days fall in.
     */
    private function syncBalancesFor(LeaveRequest $request, ?array $years = null): void
    {
        $years ??= LeaveDay::where('leave_request_id', $request->id)
            ->distinct()
            ->pluck('year')
            ->all();

        if (empty($years)) {
            $years = [Carbon::parse($request->start_date)->year];
        }

        $employee = $request->employee ?? Employee::find($request->employee_id);
        $leaveType = $request->leaveType ?? LeaveType::find($request->leave_type_id);

        if (! $employee || ! $leaveType) {
            return;
        }

        foreach (array_unique($years) as $year) {
            $this->engine->syncBalance($employee, $leaveType, (int) $year);
        }
    }

    /**
     * Ensure the acting user is allowed to act on the current approval stage:
     * the designated approver for that stage, or an admin (leave.manage). When
     * no approver is configured for the stage, any leave.approve user may act.
     */
    private function authorizeStage(LeaveRequest $request, User $actor, string $level): void
    {
        // HR managers can always act.
        if ($actor->can('leave.manage')) {
            return;
        }

        $designated = $level === 'manager'
            ? $request->leaveType->manager_approver_id
            : $request->leaveType->director_approver_id;

        // System-wide Manager / Director approvers can act on the matching stage.
        $hasFlag = $level === 'manager' ? (bool) $actor->is_manager : (bool) $actor->is_director;

        $allowed = $hasFlag
            || ($designated && $actor->id === $designated)
            || (! $designated && $actor->can('leave.approve'));

        abort_unless($allowed, 403, 'You are not allowed to approve this request at this stage.');
    }

    private function loadFull(LeaveRequest $request): LeaveRequest
    {
        return $request->load([
            'employee:id,employee_no,first_name,last_name',
            'leaveType:id,name,code,is_paid,requires_director_approval',
            'approver:id,first_name,last_name',
            'managerApprover:id,first_name,last_name',
            'directorApprover:id,first_name,last_name',
        ]);
    }

    // ── Notifications ──

    private function notifyManager(LeaveRequest $request): void
    {
        $ids = User::where('is_manager', true)->pluck('id')->all();
        $ids[] = $request->leaveType?->manager_approver_id;

        $this->sendLeaveNotificationToMany(
            $ids,
            'New leave request',
            "{$request->employee?->full_name} submitted a {$request->leaveType?->name} request awaiting approval.",
            'leave_submitted',
            $request->id,
        );
    }

    private function notifyDirector(LeaveRequest $request): void
    {
        $ids = User::where('is_director', true)->pluck('id')->all();
        $ids[] = $request->leaveType?->director_approver_id;

        $this->sendLeaveNotificationToMany(
            $ids,
            'Leave awaiting director approval',
            "{$request->employee?->full_name}'s {$request->leaveType?->name} request was approved by the manager and awaits director approval.",
            'leave_awaiting_director',
            $request->id,
        );
    }

    private function sendLeaveNotificationToMany(array $userIds, string $title, string $message, string $action, int $leaveId): void
    {
        foreach (array_unique(array_filter($userIds)) as $userId) {
            $this->sendLeaveNotification($userId, $title, $message, $action, $leaveId);
        }
    }

    private function notifyEmployee(LeaveRequest $request, string $decision): void
    {
        $this->sendLeaveNotification(
            $request->employee?->user_id,
            "Leave request {$decision}",
            "Your {$request->leaveType?->name} request has been {$decision}.",
            "leave_{$decision}",
            $request->id,
        );
    }

    private function sendLeaveNotification(?int $userId, string $title, string $message, string $action, int $leaveId): void
    {
        if (! $userId) {
            return;
        }

        try {
            $user = User::find($userId);
            $user?->notify(new LeaveStatusNotification($title, $message, $action, $leaveId));
        } catch (\Throwable $e) {
            // Never let a notification/mail failure (e.g. SMTP not configured) break the workflow.
            Log::warning('Leave notification failed: '.$e->getMessage());
        }
    }

    public function cancel(int $id): LeaveRequest
    {
        return DB::transaction(function () use ($id) {
            $request = LeaveRequest::findOrFail($id);
            $wasApproved = $request->status === 'approved';

            $request->update(['status' => 'cancelled']);

            if ($this->engine->enabled()) {
                // Dropping the per-day rows restores the balance on its own —
                // plan 27.13. Sync afterwards so the stored snapshot agrees.
                $this->engine->releaseDays($request);
                $this->syncBalancesFor($request, [Carbon::parse($request->start_date)->year, Carbon::parse($request->end_date)->year]);
            } elseif ($wasApproved) {
                $year = Carbon::parse($request->start_date)->year;
                $balance = LeaveBalance::where('employee_id', $request->employee_id)
                    ->where('leave_type_id', $request->leave_type_id)
                    ->where('year', $year)
                    ->first();

                if ($balance) {
                    $balance->used_days = max(0, (float) $balance->used_days - (float) $request->days_count);
                    $balance->remaining_days = (float) $balance->entitled_days - (float) $balance->used_days;
                    $balance->save();
                }
            }

            return $request->load([
                'employee:id,employee_no,first_name,last_name',
                'leaveType:id,name,code,is_paid',
            ]);
        });
    }

    private function ensureBalance(int $employeeId, int $leaveTypeId, int $year): LeaveBalance
    {
        $balance = LeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();

        if (! $balance) {
            $type = LeaveType::findOrFail($leaveTypeId);
            $entitled = (float) $type->default_days_per_year;
            $balance = LeaveBalance::create([
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'year' => $year,
                'entitled_days' => $entitled,
                'used_days' => 0,
                'remaining_days' => $entitled,
            ]);
        }

        return $balance;
    }

    // ── Balances ──

    /**
     * The balances shown to an employee.
     *
     * With the engine on these are computed from the same source the engine
     * enforces against, rather than read from the stored leave_balances rows.
     *
     * Those two used to be able to disagree, and on real data they did: an
     * employee could be shown 12 days remaining while the system would refuse
     * anything past 6. There is no explanation the employee can act on when that
     * happens, so the complaint goes to HR instead of the system (plan 7.3.7).
     * One source of truth removes the whole class of problem.
     *
     * The stored rows are still written by syncBalance() — they remain the audit
     * record of what was calculated and under which policy (rule_snapshot) — but
     * they are no longer what the employee is shown.
     *
     * With the engine off, the original stored-row behaviour is returned
     * unchanged, so nothing shifts on production until the engine is enabled.
     */
    public function balanceFor(int $employeeId, int $year): Collection
    {
        if (! $this->engine->enabled()) {
            return LeaveBalance::with('leaveType:id,name,code,is_paid')
                ->where('employee_id', $employeeId)
                ->where('year', $year)
                ->get();
        }

        $employee = Employee::findOrFail($employeeId);

        $stored = LeaveBalance::where('employee_id', $employeeId)
            ->where('year', $year)
            ->get()
            ->keyBy('leave_type_id');

        return LeaveType::active()
            ->orderBy('name')
            ->get()
            ->map(function (LeaveType $type) use ($employee, $year, $stored) {
                $summary = $this->engine->summary($employee, $type, $year);

                return [
                    'id' => $stored[$type->id]->id ?? null,
                    'employee_id' => $employee->id,
                    'leave_type_id' => $type->id,
                    'year' => $year,
                    'entitled_days' => $summary['entitled'],
                    'carried_forward' => $summary['carried_forward'],
                    'adjustment_days' => $summary['adjustment'],
                    'used_days' => $summary['taken'],
                    // Awaiting approval, already held against the balance so two
                    // overlapping requests cannot both be granted (plan 8.4).
                    'pending_days' => $summary['pending'],
                    'remaining_days' => $summary['available'],
                    // Present when this type shares a cap with another, so the UI
                    // can explain a figure that otherwise appears to move on its
                    // own (plan 7.3.7).
                    'pool' => $summary['pool'],
                    'leave_type' => [
                        'id' => $type->id,
                        'name' => $type->name,
                        'code' => $type->code,
                        'is_paid' => $type->is_paid,
                    ],
                ];
            })
            ->values();
    }

    // ── Leave Types ──

    public function leaveTypes(): Collection
    {
        return LeaveType::active()
            ->with(['managerApprover:id,first_name,last_name', 'directorApprover:id,first_name,last_name'])
            ->orderBy('name')->get();
    }

    public function createType(array $data): LeaveType
    {
        return LeaveType::create($data);
    }

    public function updateType(int $id, array $data): LeaveType
    {
        $type = LeaveType::findOrFail($id);
        $type->update($data);

        return $type;
    }

    public function deleteType(int $id): void
    {
        LeaveType::findOrFail($id)->delete();
    }

    // ── Attachment ──

    public function downloadAttachment(int $id)
    {
        $request = LeaveRequest::findOrFail($id);
        abort_if(! $request->attachment_path || ! Storage::disk('local')->exists($request->attachment_path), 404);

        return Storage::disk('local')->download($request->attachment_path);
    }
}
