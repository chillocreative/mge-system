<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoAttachment extends Model
{
    protected $fillable = ['memo_id', 'file_name', 'file_path', 'file_size', 'mime_type'];

    protected $appends = ['human_size'];

    public function memo(): BelongsTo
    {
        return $this->belongsTo(Memo::class);
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
