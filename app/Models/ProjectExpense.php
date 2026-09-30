<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectExpense extends Model
{
    protected $fillable = ['project_id', 'expense_date', 'category', 'description', 'vendor', 'invoice_no', 'do_no', 'amount', 'status', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['expense_date' => 'date:Y-m-d', 'amount' => 'decimal:2'];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
