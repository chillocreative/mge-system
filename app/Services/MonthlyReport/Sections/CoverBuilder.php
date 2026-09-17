<?php

namespace App\Services\MonthlyReport\Sections;

use App\Models\ProjectParty;
use App\Services\MonthlyReport\ReportContext;

final class CoverBuilder extends AbstractBuilder
{
    private const WORDS = [1 => 'SATU', 'DUA', 'TIGA', 'EMPAT', 'LIMA', 'ENAM', 'TUJUH', 'LAPAN', 'SEMBILAN', 'SEPULUH', 'SEBELAS', 'DUA BELAS'];

    public function build(ReportContext $ctx): array
    {
        $party = fn (string $role) => ($p = $ctx->party($role)) ? $this->party($p) : null;
        $contractor = $ctx->party('contractor');

        return [
            'schema' => 1,
            'report_no' => $ctx->reportNo,
            'report_no_words' => sprintf('%02d (%s)', $ctx->reportNo, self::WORDS[$ctx->reportNo] ?? (string) $ctx->reportNo),
            'period_label' => $ctx->periodLabel(),
            'period_start' => $ctx->period->period_start->toDateString(),
            'period_end' => $ctx->period->period_end->toDateString(),
            'project_title' => $ctx->project->name,
            'contract_no' => $ctx->contract?->contract_no ?? $ctx->project->code,
            'client' => $party('owner'),
            'so' => $party('superintending_officer'),
            'consultant' => $party('consultant'),
            'contractor' => $party('contractor'),
            'signatories' => [
                ['slot' => 'prepared', 'name' => $contractor?->contacts->first()?->name ?? '', 'designation' => $contractor?->contacts->first()?->designation ?? 'Project Manager', 'company' => $contractor?->name ?? ''],
                ['slot' => 'verified', 'name' => $ctx->party('consultant')?->contacts->first()?->name ?? '', 'designation' => '', 'company' => $ctx->party('consultant')?->name ?? ''],
                ['slot' => 'accepted', 'name' => '', 'designation' => '', 'company' => $ctx->party('superintending_officer')?->name ?? ''],
            ],
        ];
    }

    private function party(ProjectParty $p): array
    {
        return ['id' => $p->id, 'name' => $p->name, 'address' => $p->address, 'has_logo' => (bool) $p->logo_path];
    }
}
