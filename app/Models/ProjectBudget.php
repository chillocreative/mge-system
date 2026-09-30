<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectBudget extends Model
{
    protected $fillable = ['project_id', 'month', 'category', 'budgeted_cost', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['month' => 'date:Y-m-d', 'budgeted_cost' => 'decimal:2'];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
