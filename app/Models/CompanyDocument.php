<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'doc_type',
        'reference_no',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'version',
        'status',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'version' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('doc_type', $type);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(fn ($q) => $q->where('title', 'like', "%{$term}%")
            ->orWhere('reference_no', 'like', "%{$term}%"));
    }
}
