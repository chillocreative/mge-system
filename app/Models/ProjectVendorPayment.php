<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ProjectVendorPayment extends Model
{
    protected $appends = ['payment_timing'];

    protected $fillable = ['project_id', 'vendor', 'invoice_no', 'do_no', 'invoice_date', 'submitted_date', 'due_date', 'paid_date', 'amount', 'status', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['invoice_date' => 'date:Y-m-d', 'submitted_date' => 'date:Y-m-d', 'due_date' => 'date:Y-m-d', 'paid_date' => 'date:Y-m-d', 'amount' => 'decimal:2'];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function getPaymentTimingAttribute(): ?string
    {
        if (! $this->submitted_date) {
            return null;
        } $due = $this->submitted_date->copy()->addDays(30);
        $end = $this->paid_date ?: Carbon::today();
        $days = $end->diffInDays($due, false);

        return $days >= 0 ? 'Early by '.$days.' days' : 'Delay by '.abs($days).' days';
    }
}
