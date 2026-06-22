<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id', 'title', 'category', 'reason', 'preferred_date', 'estimated_cost',
        'status', 'reviewed_by', 'reviewed_at', 'review_notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'estimated_cost' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
}
