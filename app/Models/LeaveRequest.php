<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    protected $fillable = [
        'employee_id', 'leave_type_id', 'start_date', 'end_date', 'days_count', 'reason',
        'attachment_path', 'status', 'approved_by', 'approved_at', 'rejection_reason', 'created_by',
        'current_approval_level', 'manager_approved_by', 'manager_approved_at',
        'director_approved_by', 'director_approved_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'days_count' => 'decimal:1',
            'approved_at' => 'datetime',
            'manager_approved_at' => 'datetime',
            'director_approved_at' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function managerApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_approved_by');
    }

    public function directorApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_approved_by');
    }

    // ── Scopes ──

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Requests pending the given user's approval at the current stage:
     * manager stage where the type's manager approver is the user, OR
     * director stage where the type's director approver is the user.
     */
    public function scopeAwaitingApprovalBy($query, int $userId)
    {
        return $query->where('status', 'pending')->where(function ($q) use ($userId) {
            $q->where(function ($m) use ($userId) {
                $m->where('current_approval_level', 'manager')
                    ->whereHas('leaveType', fn ($t) => $t->where('manager_approver_id', $userId));
            })->orWhere(function ($d) use ($userId) {
                $d->where('current_approval_level', 'director')
                    ->whereHas('leaveType', fn ($t) => $t->where('director_approver_id', $userId));
            });
        });
    }
}
