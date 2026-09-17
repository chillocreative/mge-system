<?php

namespace App\Services\MonthlyReport\Sections;

use App\Models\ProjectProgressPeriod;
use App\Services\MonthlyReport\ReportContext;
use App\Services\MonthlyReport\SectionBuilder;
use App\Services\MonthlyReport\SectionRegistry;

abstract class AbstractBuilder implements SectionBuilder
{
    public function __construct(protected readonly string $key) {}

    public function key(): string
    {
        return $this->key;
    }

    public function title(): string
    {
        return SectionRegistry::TITLES[$this->key];
    }

    abstract public function build(ReportContext $ctx): array;

    protected function money(?float $amount): ?string
    {
        return $amount === null ? null : 'RM '.number_format($amount, 2);
    }

    /**
     * All progress periods for the project, keyed by the "Y-m" of their
     * period_end. When two periods end in the same month, the one with the
     * higher period_no wins (periods are loaded ordered by period_no
     * ascending, so later periods overwrite earlier ones in the map).
     *
     * @return array<string, ProjectProgressPeriod>
     */
    protected function periodsByMonth(ReportContext $ctx): array
    {
        $map = [];

        foreach (ProjectProgressPeriod::where('project_id', $ctx->project->id)->orderBy('period_no')->get() as $period) {
            $map[$period->period_end->format('Y-m')] = $period;
        }

        return $map;
    }
}
