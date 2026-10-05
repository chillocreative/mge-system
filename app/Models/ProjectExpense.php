<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectExpense extends Model
{
    protected $fillable = ['project_id', 'expense_date', 'category', 'description', 'quantity', 'unit', 'unit_price', 'amount', 'payment_method', 'invoice_no', 'do_no', 'vendor', 'amount_source_mismatch', 'status', 'notes', 'created_by'];

    protected $appends = ['amount_calculated'];

    protected function casts(): array
    {
        return ['expense_date' => 'date:Y-m-d', 'quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'amount' => 'decimal:2', 'amount_source_mismatch' => 'boolean'];
    }

    public function getAmountCalculatedAttribute(): ?float
    {
        if ($this->quantity === null || $this->unit_price === null) {
            return null;
        }

        return round((float) $this->quantity * (float) $this->unit_price, 2);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
