<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectProgrammeVersion extends Model
{
    protected $fillable = [
        'project_id',
        'label',
        'status_date',
        'source_type',
        'source_file_path',
        'source_file_name',
        'is_current',
        'activity_count',
        'imported_by',
    ];

    protected function casts(): array
    {
        return [
            'status_date' => 'date:Y-m-d',
            'is_current' => 'boolean',
            'activity_count' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProgrammeActivity::class, 'version_id');
    }
}
