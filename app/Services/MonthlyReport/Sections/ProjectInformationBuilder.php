<?php

namespace App\Services\MonthlyReport\Sections;

use App\Services\MonthlyReport\ReportContext;
use App\Support\RinggitWords;
use Carbon\Carbon;

final class ProjectInformationBuilder extends AbstractBuilder
{
    public function build(ReportContext $ctx): array
    {
        $c = $ctx->contract;
        $d = fn ($v) => $v ? Carbon::parse($v)->format('d/m/Y') : '-';
        $rows = [
            ['label' => 'Project Title', 'value' => $ctx->project->name],
            ['label' => 'Contract Number', 'value' => $c?->contract_no ?? $ctx->project->code],
            ['label' => 'Contract Sum', 'value' => $c?->contract_sum !== null ? $this->money((float) $c->contract_sum).' ('.RinggitWords::spell($c->contract_sum).')' : '-'],
            ['label' => 'Performance Guarantee Fund / WJP', 'value' => $c?->performance_bond_amount !== null ? $this->money((float) $c->performance_bond_amount).' ('.RinggitWords::spell($c->performance_bond_amount).')' : '-'],
            ['label' => 'Duration of Completion', 'value' => $c?->duration_months ? $c->duration_months.' MONTHS' : '-'],
            ['label' => 'LAD', 'value' => $c?->lad_per_day !== null ? $this->money((float) $c->lad_per_day).'/day' : '-'],
            ['label' => 'Contract Period', 'value' => 'COMPLETION: '.($c?->duration_months ? $c->duration_months.' MONTHS' : '-')."\nDLP: ".($c?->dlp_months ? $c->dlp_months.' MONTHS' : '-')],
            ['label' => 'Date Contract', 'value' => 'Possession Date: '.$d($c?->possession_date)."\nCompletion Date: ".$d($c?->completion_date)."\nDLP Start Date: ".$d($c?->dlp_start_date)."\nDLP Completion Date: ".$d($c?->dlp_end_date)],
            ['label' => 'CIDB Registration', 'value' => $c?->cidb_registration ?: 'TBA'],
            ['label' => 'Insurance', 'value' => $this->insurances($c?->insurances ?? [])],
        ];

        return ['schema' => 1, 'rows' => $rows];
    }

    private function insurances(array $list): string
    {
        if ($list === []) {
            return '-';
        }
        $d = fn ($v) => $v ? Carbon::parse($v)->format('d/m/Y') : '-';
        $lines = [];
        foreach ($list as $i) {
            $lines[] = ($i['type'] ?? 'Insurance').' : '.($i['insurer'] ?? '-')
                ."\nPolicy Number : ".($i['policy_no'] ?? '-')
                ."\nPeriod of Insurance : ".$d($i['period_from'] ?? null).' until '.$d($i['period_to'] ?? null)
                ."\nMaintenance Period : ".$d($i['maintenance_from'] ?? null).' until '.$d($i['maintenance_to'] ?? null);
        }

        return implode("\n\n", $lines);
    }
}
