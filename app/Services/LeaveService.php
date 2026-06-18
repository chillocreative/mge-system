<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
            $data['created_by'] = $userId;

            $request = LeaveRequest::create($data);

            return $request->load([
                'employee:id,employee_no,first_name,last_name',
                'leaveType:id,name,code,is_paid',
            ]);
        });
    }

    public function approve(int $id, int $approverId): LeaveRequest
    {
        return DB::transaction(function () use ($id, $approverId) {
            $request = LeaveRequest::findOrFail($id);

            $request->update([
                'status' => 'approved',
                'approved_by' => $approverId,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            $year = Carbon::parse($request->start_date)->year;
            $balance = $this->ensureBalance($request->employee_id, $request->leave_type_id, $year);

            $balance->used_days = (float) $balance->used_days + (float) $request->days_count;
            $balance->remaining_days = (float) $balance->entitled_days - (float) $balance->used_days;
            $balance->save();

            return $request->load([
                'employee:id,employee_no,first_name,last_name',
                'leaveType:id,name,code,is_paid',
                'approver:id,first_name,last_name',
            ]);
        });
    }

    public function reject(int $id, int $approverId, ?string $reason = null): LeaveRequest
    {
        $request = LeaveRequest::findOrFail($id);

        $request->update([
            'status' => 'rejected',
            'approved_by' => $approverId,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $request->load([
            'employee:id,employee_no,first_name,last_name',
            'leaveType:id,name,code,is_paid',
            'approver:id,first_name,last_name',
        ]);
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
        return LeaveType::active()->orderBy('name')->get();
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
