<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceChecklistItem extends Model
{
    protected $fillable = ['checklist_id', 'item_text', 'status', 'notes', 'sort_order'];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(ComplianceChecklist::class, 'checklist_id');
    }
}
