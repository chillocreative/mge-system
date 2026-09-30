<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ProjectSubcontractorClaim extends Model
{
    protected $appends = ['payment_timing'];

    protected $fillable = ['project_id', 'subcontractor', 'claim_no', 'invoice_no', 'do_no', 'submitted_date', 'certified_date', 'paid_date', 'amount', 'certified_amount', 'status', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['submitted_date' => 'date:Y-m-d', 'certified_date' => 'date:Y-m-d', 'paid_date' => 'date:Y-m-d', 'amount' => 'decimal:2', 'certified_amount' => 'decimal:2'];
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
