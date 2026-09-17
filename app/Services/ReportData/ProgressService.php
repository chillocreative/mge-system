<?php

namespace App\Services\ReportData;

use App\Models\ProjectInvoice;
use App\Models\ProjectScheduleBaseline;
use Carbon\Carbon;

class ProgressService
{
    public function compute(array $p): array
    {
        $p['physical_variance'] = round((float) ($p['physical_actual_pct'] ?? 0) - (float) ($p['physical_scheduled_pct'] ?? 0), 2);
        $p['financial_variance'] = round((float) ($p['financial_actual_pct'] ?? 0) - (float) ($p['financial_scheduled_pct'] ?? 0), 2);

        if (($p['ahead_delay_days'] ?? null) === null) {
            $days = (int) ($p['planning_days_completion'] ?? 0);
            $p['ahead_delay_days'] = (int) round($p['physical_variance'] / 100 * $days);
        }

        if (empty($p['physical_status'])) {
            $p['physical_status'] = match (true) {
                $p['physical_variance'] > 0 => 'AHEAD',
                $p['physical_variance'] < 0 => 'DELAY',
                default => 'ON TRACK',
            };
        }

        return $p;
    }

    public function scheduledFor(int $projectId, Carbon $periodEnd): ?ProjectScheduleBaseline
    {
        return ProjectScheduleBaseline::where('project_id', $projectId)
            ->where('month', $periodEnd->copy()->startOfMonth()->toDateString())
            ->first();
    }

    /** Sum of certified claim amounts up to the period end, as a % of contract sum. */
    public function financialActual(int $projectId, Carbon $periodEnd, ?float $contractSum): array
    {
        $amount = (float) ProjectInvoice::where('project_id', $projectId)
            ->where('type', 'client')
            ->whereNotNull('payment_cert_date')
            ->where('payment_cert_date', '<=', $periodEnd->toDateString())
            ->sum('amount');
        $pct = $contractSum ? round($amount / $contractSum * 100, 2) : 0.0;

        return ['amount' => $amount, 'pct' => $pct];
    }
}
