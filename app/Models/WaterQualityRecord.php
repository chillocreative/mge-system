<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WaterQualityRecord extends Model
{
    use SoftDeletes;

    public const POINTS = ['W1', 'W2', 'W3', 'W4'];

    public const CONDITION_OPTIONS = [
        'odour' => ['No', 'Slightly', 'Medium', 'Strong'],
        'floating' => ['Yes', 'No'],
        'area' => ['Residential', 'Forest', 'Bushes', 'Plantation', 'Construction'],
        'flow' => ['Stagnant', 'Slow', 'Medium', 'Fast'],
        'colour' => ['Clear', 'Slightly Cloudy', 'Med. Cloudy', 'Very Cloudy'],
        'level' => ['Shallow', 'Medium', 'Deep'],
        'weather' => ['Sunny', 'Cloudy', 'Light Rain', 'Med. Rain', 'Heavy Rain', 'Gloomy'],
    ];

    public const INSITU_PARAMS = [
        'temperature' => '°C',
        'ph' => '-',
        'do' => 'mg/L',
    ];

    public const LAB_PARAMS = [
        'cod' => 'mg/L',
        'bod' => 'mg/L',
        'tss' => 'mg/L',
        'oil_grease' => 'mg/L',
        'ecoli' => 'CFU/100mL',
        'ammoniacal_n' => 'mg/L',
        'temperature' => '°C',
        'ph' => '-',
        'do' => 'mg/L',
    ];

    protected $fillable = [
        'project_id',
        'sample_date',
        'sample_time',
        'data_collector',
        'witness',
        'conditions',
        'insitu',
        'lab',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'sample_date' => 'date',
            'conditions' => 'array',
            'insitu' => 'array',
            'lab' => 'array',
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

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeBetween($query, ?string $from, ?string $to)
    {
        if ($from) {
            $query->whereDate('sample_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('sample_date', '<=', $to);
        }

        return $query;
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(fn ($q) => $q->where('data_collector', 'like', "%{$term}%")
            ->orWhere('witness', 'like', "%{$term}%")
            ->orWhere('notes', 'like', "%{$term}%"));
    }
}
