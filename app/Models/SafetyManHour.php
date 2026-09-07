<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Man-hours worked, entered per project per month — the denominator for LTIFR
 * and the severity rate (Ciri 25). See the migration for why it is recorded
 * rather than inferred from attendance.
 */
class SafetyManHour extends Model
{
    use HasFactory;

    protected $table = 'safety_man_hours';

    protected $fillable = ['project_id', 'year', 'month', 'man_hours', 'recorded_by'];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'man_hours' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
