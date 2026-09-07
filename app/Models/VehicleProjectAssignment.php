<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleProjectAssignment extends Model
{
    protected $fillable = [
        'vehicle_id', 'project_id', 'assigned_at', 'released_at', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'date:Y-m-d',
            'released_at' => 'date:Y-m-d',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
