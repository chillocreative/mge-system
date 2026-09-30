<?php

namespace App\Services;

use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AssetUsageReportService
{
    public function generate(string $month, ?string $category = null): array
    {
        $monthStart = CarbonImmutable::createFromFormat('!Y-m', $month)->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();
        $today = CarbonImmutable::today();

        $vehicles = Vehicle::query()
            ->when($category, fn ($query) => $query->where('category', $category))
            ->with([
                'projectAssignments.project:id,name,code',
                'maintenanceLogs' => fn ($query) => $query->orderByDesc('performed_date'),
                'documents' => fn ($query) => $query->where('doc_type', 'road_tax')->orderByDesc('expiry_date'),
            ])
            ->orderBy('category')
            ->orderBy('registration_no')
            ->get();

        $rows = $vehicles->map(fn (Vehicle $vehicle) => $this->row($vehicle, $monthStart, $monthEnd, $today))->values();

        return [
            'month' => $monthStart->format('Y-m'),
            'month_label' => $monthStart->format('F Y'),
            'summary' => [
                'total_assets' => $rows->count(),
                'used_assets' => $rows->where('monthly_usage_days', '>', 0)->count(),
                'monthly_usage_days' => $rows->sum('monthly_usage_days'),
                'maintenance_due' => $rows->whereIn('maintenance_status', ['overdue', 'due_soon'])->count(),
                'road_tax_attention' => $rows->whereIn('road_tax_status', ['expired', 'expiring'])->count(),
            ],
            'rows' => $rows->all(),
        ];
    }

    private function row(Vehicle $vehicle, CarbonImmutable $monthStart, CarbonImmutable $monthEnd, CarbonImmutable $today): array
    {
        $assignments = $vehicle->projectAssignments;
        $monthlyIntervals = $this->intervals($assignments, $monthStart, $monthEnd);
        $totalIntervals = $this->intervals($assignments, null, $today);
        $maintenance = $vehicle->maintenanceLogs->first();
        $roadTax = $vehicle->documents->first();
        $maintenanceDue = $maintenance?->next_due_date
            ? CarbonImmutable::parse($maintenance->next_due_date->format('Y-m-d'))
            : null;
        $roadTaxExpiry = $roadTax?->expiry_date
            ? CarbonImmutable::parse($roadTax->expiry_date->format('Y-m-d'))
            : null;

        return [
            'id' => $vehicle->id,
            'asset_no' => $vehicle->registration_no,
            'asset' => trim($vehicle->make.' '.($vehicle->model ?? '')),
            'category' => $vehicle->category ?? ($vehicle->type === 'machinery' ? 'machine' : 'vehicle'),
            'type' => $vehicle->custom_type ?: $vehicle->type,
            'status' => $vehicle->status,
            'projects' => $assignments
                ->filter(fn ($assignment) => $this->overlaps($assignment->assigned_at, $assignment->released_at, $monthStart, $monthEnd))
                ->pluck('project.name')->filter()->unique()->values()->implode(', '),
            'monthly_usage_days' => $this->duration($monthlyIntervals),
            'monthly_assignment_count' => $assignments
                ->filter(fn ($assignment) => $this->overlaps($assignment->assigned_at, $assignment->released_at, $monthStart, $monthEnd))
                ->count(),
            'total_usage_days' => $this->duration($totalIntervals),
            'last_maintenance_date' => $maintenance?->performed_date?->format('Y-m-d'),
            'maintenance_due_date' => $maintenanceDue?->format('Y-m-d'),
            'maintenance_status' => $this->maintenanceStatus($maintenance?->status, $maintenanceDue, $today),
            'road_tax_expiry_date' => $roadTaxExpiry?->format('Y-m-d'),
            'road_tax_days_remaining' => $roadTaxExpiry ? (int) $today->diffInDays($roadTaxExpiry, false) : null,
            'road_tax_status' => $this->roadTaxStatus($roadTaxExpiry, $today),
        ];
    }

    private function intervals(Collection $assignments, ?CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd): array
    {
        $intervals = $assignments->map(function ($assignment) use ($rangeStart, $rangeEnd) {
            $start = CarbonImmutable::parse($assignment->assigned_at->format('Y-m-d'));
            $end = $assignment->released_at
                ? CarbonImmutable::parse($assignment->released_at->format('Y-m-d'))
                : $rangeEnd;

            if ($rangeStart && $start->lt($rangeStart)) {
                $start = $rangeStart;
            }
            if ($end->gt($rangeEnd)) {
                $end = $rangeEnd;
            }

            return $start->lte($end) ? [$start, $end] : null;
        })->filter()->sortBy(fn ($interval) => $interval[0]->timestamp)->values();

        $merged = [];
        foreach ($intervals as [$start, $end]) {
            $last = array_key_last($merged);
            if ($last !== null && $start->lte($merged[$last][1]->addDay())) {
                if ($end->gt($merged[$last][1])) {
                    $merged[$last][1] = $end;
                }
            } else {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }

    private function duration(array $intervals): int
    {
        return (int) collect($intervals)->sum(fn ($interval) => $interval[0]->diffInDays($interval[1]) + 1);
    }

    private function overlaps($assignedAt, $releasedAt, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        $assigned = CarbonImmutable::parse($assignedAt->format('Y-m-d'));
        $released = $releasedAt ? CarbonImmutable::parse($releasedAt->format('Y-m-d')) : $end;

        return $assigned->lte($end) && $released->gte($start);
    }

    private function maintenanceStatus(?string $status, ?CarbonImmutable $due, CarbonImmutable $today): string
    {
        if ($due?->lt($today)) {
            return 'overdue';
        }
        if ($due && $due->lte($today->addDays(30))) {
            return 'due_soon';
        }

        return $status ?: ($due ? 'up_to_date' : 'no_record');
    }

    private function roadTaxStatus(?CarbonImmutable $expiry, CarbonImmutable $today): string
    {
        if (! $expiry) {
            return 'no_record';
        }
        if ($expiry->lt($today)) {
            return 'expired';
        }

        return $expiry->lte($today->addDays(30)) ? 'expiring' : 'valid';
    }
}
