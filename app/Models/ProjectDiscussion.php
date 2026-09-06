<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectDiscussion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id', 'parent_id', 'body', 'posted_by',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeForProject($q, int $id)
    {
        return $q->where('project_id', $id);
    }

    public function scopeTopLevel($q)
    {
        return $q->whereNull('parent_id');
    }
}
