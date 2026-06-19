<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'invoice_no',
        'invoice_date',
        'amount',
        'status',
        'client_approved_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'client_approved_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectInvoiceFile::class);
    }

    public function scopeByStatus($q, string $s)
    {
        return $q->where('status', $s);
    }

    public function scopeForProject($q, int $id)
    {
        return $q->where('project_id', $id);
    }
}
