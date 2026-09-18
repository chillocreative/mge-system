<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgrammeActivity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'version_id',
        'seq',
        'outline_level',
        'name',
        'duration_days',
        'start',
        'finish',
        'actual_pct',
        'plan_pct',
        'is_summary',
    ];

    protected function casts(): array
    {
        return [
            'seq' => 'integer',
            'outline_level' => 'integer',
            'duration_days' => 'integer',
            'start' => 'date:Y-m-d',
            'finish' => 'date:Y-m-d',
            'actual_pct' => 'decimal:2',
            'plan_pct' => 'decimal:2',
            'is_summary' => 'boolean',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProjectProgrammeVersion::class, 'version_id');
    }
}
