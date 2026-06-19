<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectInvoiceFile extends Model
{
    protected $fillable = [
        'project_invoice_id',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ProjectInvoice::class, 'project_invoice_id');
    }
}
