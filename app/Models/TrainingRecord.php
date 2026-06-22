<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id', 'title', 'provider', 'category', 'training_date', 'end_date',
        'duration_days', 'cost', 'hrdf_claimable', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'training_date' => 'date',
            'end_date' => 'date',
            'duration_days' => 'decimal:1',
            'cost' => 'decimal:2',
            'hrdf_claimable' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
}
