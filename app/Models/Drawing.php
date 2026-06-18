<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Drawing extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'drawing_no',
        'reference_no',
        'revision',
        'tag',
        'discipline',
        'project_id',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'status',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeByDiscipline($query, string $discipline)
    {
        return $query->where('discipline', $discipline);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(fn ($q) => $q->where('title', 'like', "%{$term}%")
            ->orWhere('drawing_no', 'like', "%{$term}%")
            ->orWhere('tag', 'like', "%{$term}%")
            ->orWhere('reference_no', 'like', "%{$term}%"));
    }
}
