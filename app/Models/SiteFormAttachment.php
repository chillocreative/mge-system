<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteFormAttachment extends Model
{
    protected $fillable = [
        'site_form_id',
        'slot',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
    ];

    public function siteForm(): BelongsTo
    {
        return $this->belongsTo(SiteForm::class);
    }
}
