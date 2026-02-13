<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolboxMeetingAttendee extends Model
{
    protected $fillable = ['toolbox_meeting_id', 'user_id', 'name'];

    public function meeting(): BelongsTo { return $this->belongsTo(ToolboxMeeting::class, 'toolbox_meeting_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function getDisplayNameAttribute(): string
    {
        if ($this->user) {
            return $this->user->first_name . ' ' . $this->user->last_name;
        }
        return $this->name ?? 'Unknown';
    }
}
