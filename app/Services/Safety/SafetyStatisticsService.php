<?php

namespace App\Services\Safety;

use App\Models\SafetyIncident;
use App\Models\SafetyManHour;

/**
 * Computes the safety KPIs from recorded incidents and man-hours (Ciri 25).
 *
 * The two headline rates follow the OSHA/DOSH conventions:
 *   LTIFR = lost-time injuries × 1,000,000 / man-hours worked
 *   Severity rate = days lost × 1,000,000 / man-hours worked
 * The 1,000,000 base expresses the rate per million man-hours. When no
 * man-hours are recorded the rates are null (not zero) — an undefined rate is
 * not the same as a perfect one.
 */
class SafetyStatisticsService
{
    private const BASE = 1_000_000;

    public function forYear(int $year, ?int $projectId = null): array
    {
        $incidents = SafetyIncident::query()
            ->whereYear('incident_date', $year)
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->get(['id', 'type', 'severity', 'days_lost']);

        $manHours = (int) SafetyManHour::query()
            ->where('year', $year)
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->sum('man_hours');

        $injuries = $incidents->where('type', 'injury');
        $lostTime = $incidents->filter(fn ($i) => (int) $i->days_lost > 0);
        $daysLost = (int) $incidents->sum('days_lost');

        $rate = fn (int $count) => $manHours > 0 ? round($count * self::BASE / $manHours, 2) : null;

        return [
            'year' => $year,
            'project_id' => $projectId,
            'man_hours' => $manHours,
            'total_incidents' => $incidents->count(),
            'total_injuries' => $injuries->count(),
            'near_misses' => $incidents->where('type', 'near_miss')->count(),
            'lost_time_injuries' => $lostTime->count(),
            'days_lost' => $daysLost,
            'ltifr' => $rate($lostTime->count()),
            'severity_rate' => $rate($daysLost),
            'by_severity' => $incidents->groupBy('severity')->map->count(),
            'by_type' => $incidents->groupBy('type')->map->count(),
        ];
    }
}
