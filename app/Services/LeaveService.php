<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\LeaveStatusNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LeaveService
{
    // ── Leave Requests ──

    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = LeaveRequest::with([
            'employee:id,employee_no,first_name,last_name',
            'leaveType:id,name,code,is_paid',
            'approver:id,first_name,last_name',
        ])->orderByDesc('created_at');

        if (!empty($filters['employee_id'])) $query->forEmployee($filters['employee_id']);
        if (!empty($filters['status'])) $query->byStatus($filters['status']);
        if (!empty($filters['leave_type_id'])) $query->where('leave_type_id', $filters['leave_type_id']);

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
            $halfDay = !empty($data['half_day']);

            $days = $halfDay ? 0.5 : ($start->diffInDays($end) + 1);

            if ($attachment) {
                $data['attachment_path'] = $attachment->store('leave/attachments', 'local');
            }

            unset($data['half_day']);

            $data['days_count'] = $days;
            $data['status'] = 'pending';
            $data['current_approval_level'] = 'manager';
            $data['created_by'] = $userId;

            $request = LeaveRequest::create($data);

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

        $year = Carbon::parse($request->start_date)->year;
        $balance = $this->ensureBalance($request->employee_id, $request->leave_type_id, $year);

        $balance->used_days = (float) $balance->used_days + (float) $request->days_count;
        $balance->remaining_days = (float) $balance->entitled_days - (float) $balance->used_days;
        $balance->save();

        $this->notifyEmployee($request, 'approved');
    }

    /**
     * Ensure the acting user is allowed to act on the current approval stage:
     * the designated approver for that stage, or an admin (leave.manage). When
     * no approver is configured for the stage, any leave.approve user may act.
     */
    private function authorizeStage(LeaveRequest $request, User $actor, string $level): void
    {
        $designated = $level === 'manager'
            ? $request->leaveType->manager_approver_id
            : $request->leaveType->director_approver_id;

        if ($designated) {
            abort_unless(
                $actor->id === $designated || $actor->can('leave.manage'),
                403,
                'You are not the designated approver for this stage.'
            );
        } else {
            abort_unless($actor->can('leave.approve'), 403, 'You are not allowed to approve this request.');
        }
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
        $this->sendLeaveNotification(
            $request->leaveType?->manager_approver_id,
            'New leave request',
            "{$request->employee?->full_name} submitted a {$request->leaveType?->name} request awaiting your approval.",
            'leave_submitted',
            $request->id,
        );
    }

    private function notifyDirector(LeaveRequest $request): void
    {
        $this->sendLeaveNotification(
            $request->leaveType?->director_approver_id,
            'Leave awaiting director approval',
            "{$request->employee?->full_name}'s {$request->leaveType?->name} request was approved by the manager and awaits your approval.",
            'leave_awaiting_director',
            $request->id,
        );
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
        if (!$userId) {
            return;
        }

        try {
            $user = User::find($userId);
            $user?->notify(new LeaveStatusNotification($title, $message, $action, $leaveId));
        } catch (\Throwable $e) {
            // Never let a notification/mail failure (e.g. SMTP not configured) break the workflow.
            Log::warning('Leave notification failed: ' . $e->getMessage());
        }
    }

    public function cancel(int $id): LeaveRequest
    {
        return DB::transaction(function () use ($id) {
            $request = LeaveRequest::findOrFail($id);
            $wasApproved = $request->status === 'approved';

            $request->update(['status' => 'cancelled']);

            if ($wasApproved) {
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

        if (!$balance) {
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

    public function balanceFor(int $employeeId, int $year): Collection
    {
        return LeaveBalance::with('leaveType:id,name,code,is_paid')
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->get();
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
        abort_if(!$request->attachment_path || !Storage::disk('local')->exists($request->attachment_path), 404);

        return Storage::disk('local')->download($request->attachment_path);
    }
}
