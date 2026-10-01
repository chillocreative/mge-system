<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrespondenceImportSource extends Model
{
    protected $fillable = [
        'source_identity', 'workbook_hash', 'source_file', 'source_sheet', 'source_row',
        'source_reference', 'status', 'project_id', 'project_correspondence_id',
        'raw_payload', 'warnings', 'error_message', 'imported_by',
    ];

    protected function casts(): array
    {
        return [
            'source_row' => 'integer',
            'raw_payload' => 'array',
            'warnings' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(ProjectCorrespondence::class, 'project_correspondence_id');
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
