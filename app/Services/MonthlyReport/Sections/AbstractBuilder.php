<?php

namespace App\Services\MonthlyReport\Sections;

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
}
