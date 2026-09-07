<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffLeaveEntitlementOverride extends Model
{
    protected $fillable = ['employee_id', 'leave_type_id', 'year', 'days', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'days' => 'decimal:2'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
