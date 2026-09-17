<?php

namespace App\Services\MonthlyReport;

use App\Models\MonthlyReport;
use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\ProjectParty;
use App\Models\ProjectProgressPeriod;
use Illuminate\Support\Collection;

final class ReportContext
{
    public function __construct(
        public readonly Project $project,
        public readonly ?ProjectContract $contract,
        public readonly ProjectProgressPeriod $period,
        public readonly ?ProjectProgressPeriod $previousPeriod,
        public readonly Collection $parties,
        public readonly int $reportNo,
        public readonly array $options = [],
    ) {}

    public static function for(MonthlyReport $report): self
    {
        $project = $report->project()->with('client')->firstOrFail();
        $period = $report->period()->firstOrFail();

        return new self(
            project: $project,
            contract: ProjectContract::where('project_id', $project->id)->where('is_main', true)->first(),
            period: $period,
            previousPeriod: ProjectProgressPeriod::where('project_id', $project->id)->where('period_no', '<', $period->period_no)->orderByDesc('period_no')->first(),
            parties: ProjectParty::where('project_id', $project->id)->whereNotNull('report_role')->with('contacts')->orderBy('sort_order')->get(),
            reportNo: (int) $report->report_no,
            options: $report->options ?? [],
        );
    }

    public function party(string $role): ?ProjectParty
    {
        return $this->parties->firstWhere('report_role', $role);
    }

    public function periodLabel(): string
    {
        return strtoupper($this->period->period_start->copy()->subDay()->format('d M Y')).' – '.strtoupper($this->period->period_end->format('d M Y'));
    }
}
