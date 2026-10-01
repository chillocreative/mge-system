<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrespondenceDetail extends Model
{
    protected $fillable = [
        'project_correspondence_id', 'category', 'discipline', 'document_reference',
        'request_kind', 'work_scope', 'work_category', 'inspection_type', 'inspection_date',
        'location', 'criticality', 'subcontractor_party_id', 'compliance_due_date',
        'complied_date', 'action_required', 'memo_nature', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'inspection_date' => 'date:Y-m-d',
            'compliance_due_date' => 'date:Y-m-d',
            'complied_date' => 'date:Y-m-d',
            'metadata' => 'array',
        ];
    }

    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(ProjectCorrespondence::class, 'project_correspondence_id');
    }

    public function subcontractorParty(): BelongsTo
    {
        return $this->belongsTo(ProjectParty::class, 'subcontractor_party_id');
    }
}
