<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MeetingMinute extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'meeting_date', 'meeting_time', 'location',
        'project_id', 'attendees', 'agenda', 'notes',
        'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date:Y-m-d',
            'attendees' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(MeetingMinuteFile::class);
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(MeetingActionItem::class);
    }

    public function scopeByStatus($q, string $s)
    {
        return $q->where('status', $s);
    }
}
