<?php

namespace App\Services\MonthlyReport\Sections;

use App\Models\ProjectProgressPeriod;
use App\Services\MonthlyReport\ReportContext;

final class WorkProgressBuilder extends AbstractBuilder
{
    public function build(ReportContext $ctx): array
    {
        $period = $ctx->period;
        $prev = $ctx->previousPeriod;

        $curLabel = 'Current ('.$period->period_end->format('d M Y').')';
        $prevLabel = $prev ? 'Previous ('.$prev->period_end->format('d M Y').')' : 'Previous (-)';

        $physicalRows = [
            ['label' => 'Scheduled Progress', 'prev' => $prev ? $this->pct((float) $prev->physical_scheduled_pct) : '-', 'cur' => $this->pct((float) $period->physical_scheduled_pct)],
            ['label' => 'Actual Progress', 'prev' => $prev ? $this->pct((float) $prev->physical_actual_pct) : '-', 'cur' => $this->pct((float) $period->physical_actual_pct)],
            ['label' => 'Variance (+ / -)', 'prev' => $prev ? $this->pct($prev->physical_variance, true) : '-', 'cur' => $this->pct($period->physical_variance, true)],
            ['label' => 'Ahead /Delay in Day', 'prev' => $prev ? $this->days($this->aheadDelayDays($prev)) : '-', 'cur' => $this->days($this->aheadDelayDays($period))],
            ['label' => 'Status', 'prev' => $prev ? $this->physicalStatus($prev) : '-', 'cur' => $this->physicalStatus($period)],
        ];

        $financialRows = [
            ['label' => 'Scheduled Progress', 'prev' => $prev ? $this->pct((float) $prev->financial_scheduled_pct) : '-', 'cur' => $this->pct((float) $period->financial_scheduled_pct)],
            ['label' => 'Actual Progress', 'prev' => $prev ? $this->pct((float) $prev->financial_actual_pct) : '-', 'cur' => $this->pct((float) $period->financial_actual_pct)],
            ['label' => 'Variance (+ / -)', 'prev' => $prev ? $this->pct($prev->financial_variance, true) : '-', 'cur' => $this->pct($period->financial_variance, true)],
            ['label' => 'Status', 'prev' => $prev ? ($prev->financial_status ?: 'No Claim') : '-', 'cur' => $period->financial_status ?: 'No Claim'],
        ];

        return [
            'schema' => 1,
            'planning_days' => $period->planning_days_completion,
            'physical' => ['prev_label' => $prevLabel, 'cur_label' => $curLabel, 'rows' => $physicalRows],
            'financial' => ['prev_label' => $prevLabel, 'cur_label' => $curLabel, 'rows' => $financialRows],
        ];
    }

    private function physicalStatus(ProjectProgressPeriod $p): string
    {
        if ($p->physical_status) {
            return $p->physical_status;
        }

        return match (true) {
            $p->physical_variance > 0 => 'AHEAD',
            $p->physical_variance < 0 => 'DELAY',
            default => 'ON TRACK',
        };
    }

    /** Mirrors ProgressService::compute's derivation — display only, never persisted. */
    private function aheadDelayDays(ProjectProgressPeriod $p): int
    {
        if ($p->ahead_delay_days !== null) {
            return (int) $p->ahead_delay_days;
        }

        $days = (int) ($p->planning_days_completion ?? 0);

        return (int) round($p->physical_variance / 100 * $days);
    }

    private function pct(?float $v, bool $signed = false): string
    {
        if ($v === null) {
            return '-';
        }

        $whole = fmod($v, 1.0) === 0.0;
        $formatted = number_format(abs($v), $whole ? 0 : 1);

        if ($signed) {
            return match (true) {
                $v > 0 => '+'.$formatted.'%',
                $v < 0 => '-'.$formatted.'%',
                default => '0%',
            };
        }

        return ($v < 0 ? '-' : '').$formatted.'%';
    }

    private function days(int $d): string
    {
        if ($d === 0) {
            return '0';
        }

        return ($d < 0 ? '-'.abs($d) : (string) $d).' Days';
    }
}
