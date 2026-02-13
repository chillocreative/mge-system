<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ToolboxMeeting extends Model
{
    protected $fillable = [
        'project_id', 'conducted_by', 'title', 'topics', 'location',
        'meeting_date', 'duration_minutes', 'notes', 'action_items',
    ];

    protected function casts(): array
    {
        return ['meeting_date' => 'date'];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function conductor(): BelongsTo { return $this->belongsTo(User::class, 'conducted_by'); }
    public function attendees(): HasMany { return $this->hasMany(ToolboxMeetingAttendee::class); }
    public function photos(): MorphMany { return $this->morphMany(ReportPhoto::class, 'photoable'); }

    public function scopeForProject($q, int $id) { return $q->where('project_id', $id); }
}
