<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvironmentDocument extends Model
{
    protected $fillable = ['category', 'ref_number', 'document_title', 'file_path', 'file_name', 'file_size', 'uploaded_by'];

    protected function casts(): array
    {
        return ['file_size' => 'integer'];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
