<?php

namespace App\Services\MonthlyReport;

use App\Models\MonthlyReport;
use App\Models\MonthlyReportSection;
use App\Models\ProjectProgressPeriod;
use App\Services\ReportData\ProgressService;
use App\Support\ReportPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MonthlyReportService
{
    /** Section keys whose overrides/notes are copied from a source report when copy_from_report_id is given. */
    private const STATIC_KEYS = ['cover', '1.2', '1.3', '1.4', '1.5', '3.6'];

    public function __construct(private ProgressService $progress) {}

    public function create(int $projectId, array $attrs, int $userId): MonthlyReport
    {
        return DB::transaction(function () use ($projectId, $attrs, $userId) {
            $period = $this->resolvePeriod($projectId, $attrs);

            $reportNo = $attrs['report_no']
                ?? ((int) MonthlyReport::where('project_id', $projectId)->lockForUpdate()->max('report_no') + 1);
            $monthLabel = $attrs['month_label'] ?? $period->period_end->format('F Y');

            $report = MonthlyReport::create([
                'project_id' => $projectId,
                'period_id' => $period->id,
                'report_no' => $reportNo,
                'title' => "Monthly Progress Report No.{$reportNo}",
                'month_label' => $monthLabel,
                'evaluation_date' => $attrs['evaluation_date'] ?? null,
                'generated_at' => now(),
                'generated_by' => $userId,
            ]);

            $ctx = ReportContext::for($report);
            $index = 0;
            foreach (SectionRegistry::all() as $key => $class) {
                $builder = SectionRegistry::make($key);
                MonthlyReportSection::create([
                    'report_id' => $report->id,
                    'key' => $key,
                    'title' => SectionRegistry::TITLES[$key],
                    'sort_order' => $index++,
                    'include' => $key !== '2.5',
                    'data' => $builder->build($ctx),
                ]);
            }

            if (! empty($attrs['copy_from_report_id'])) {
                $from = MonthlyReport::where('project_id', $projectId)->find($attrs['copy_from_report_id']);
                if ($from) {
                    $this->duplicateStatic($from, $report);
                }
            }

            return $report->fresh(['sections', 'project', 'period']);
        });
    }

    public function regenerate(MonthlyReport $r, ?string $key = null): MonthlyReport
    {
        $ctx = ReportContext::for($r);
        $keys = $key ? [$key] : array_keys(SectionRegistry::all());

        foreach ($keys as $k) {
            $section = $r->sections()->where('key', $k)->first();
            if (! $section) {
                continue;
            }
            $builder = SectionRegistry::make($k);
            $section->update([
                'data' => $builder->build($ctx),
                'title' => SectionRegistry::TITLES[$k],
                'regenerated_at' => now(),
            ]);
        }

        return $r->fresh(['sections', 'project', 'period']);
    }

    public function saveSection(MonthlyReport $r, string $key, array $payload): MonthlyReportSection
    {
        if ($r->isFinal()) {
            throw ValidationException::withMessages(['report' => ['Report is finalised and cannot be edited.']]);
        }

        $section = $r->sections()->where('key', $key)->firstOrFail();

        $update = [];

        if (array_key_exists('overrides', $payload)) {
            $overrides = $payload['overrides'] ?? [];
            $unknown = SectionMerger::validateOverrides($section->data ?? [], $overrides);
            if ($unknown !== []) {
                throw ValidationException::withMessages(['overrides' => ['Unknown override key(s): '.implode(', ', $unknown)]]);
            }
            $update['overrides'] = $overrides ?: null;
        }

        if (array_key_exists('notes', $payload)) {
            $update['notes'] = $payload['notes'];
        }

        if (array_key_exists('include', $payload)) {
            $update['include'] = $payload['include'];
        }

        $section->update($update);

        return $section->fresh();
    }

    public function finalise(MonthlyReport $r, int $userId): MonthlyReport
    {
        $missing = $r->sections()->where('include', true)->get()->filter(fn ($s) => empty($s->data));
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['sections' => ['All included sections must have generated data before finalising.']]);
        }

        $r->update([
            'status' => MonthlyReport::STATUS_FINAL,
            'finalised_at' => now(),
            'finalised_by' => $userId,
        ]);

        return $r->fresh(['sections', 'project', 'period']);
    }

    public function reopen(MonthlyReport $r): MonthlyReport
    {
        $r->update([
            'status' => MonthlyReport::STATUS_DRAFT,
            'finalised_at' => null,
            'finalised_by' => null,
        ]);

        return $r->fresh(['sections', 'project', 'period']);
    }

    public function duplicateStatic(MonthlyReport $from, MonthlyReport $to): void
    {
        $sourceSections = $from->sections()->whereIn('key', self::STATIC_KEYS)->get()->keyBy('key');

        foreach (self::STATIC_KEYS as $key) {
            $source = $sourceSections->get($key);
            if (! $source) {
                continue;
            }
            $to->sections()->where('key', $key)->update([
                'overrides' => $source->overrides,
                'notes' => $source->notes,
            ]);
        }
    }

    private function resolvePeriod(int $projectId, array $attrs): ProjectProgressPeriod
    {
        if (! empty($attrs['period_id'])) {
            return ProjectProgressPeriod::where('project_id', $projectId)->findOrFail($attrs['period_id']);
        }

        $project = \App\Models\Project::findOrFail($projectId);
        $end = Carbon::parse($attrs['period_end']);
        $bounds = ReportPeriod::containing((int) $project->report_cutoff_day, $end);

        $baseline = $this->progress->scheduledFor($projectId, $bounds['end']);
        $contract = \App\Models\ProjectContract::where('project_id', $projectId)->where('is_main', true)->first();
        $fin = $this->progress->financialActual($projectId, $bounds['end'], $contract?->contract_sum !== null ? (float) $contract->contract_sum : null);
        $lastNo = ProjectProgressPeriod::where('project_id', $projectId)->max('period_no');

        $payload = [
            'project_id' => $projectId,
            'period_no' => (int) $lastNo + 1,
            'period_start' => $bounds['start']->toDateString(),
            'period_end' => $bounds['end']->toDateString(),
            'physical_scheduled_pct' => $baseline?->scheduled_physical_pct !== null ? (float) $baseline->scheduled_physical_pct : null,
            'financial_scheduled_pct' => $baseline?->scheduled_financial_pct !== null ? (float) $baseline->scheduled_financial_pct : null,
            'financial_actual_amount' => $fin['amount'],
            'financial_actual_pct' => $fin['pct'],
            'planning_days_completion' => $contract?->possession_date && $contract?->completion_date
                ? (int) abs(Carbon::parse($contract->possession_date)->diffInDays(Carbon::parse($contract->completion_date))) : null,
        ];
        $payload = $this->progress->compute($payload);
        unset($payload['physical_variance'], $payload['financial_variance']);

        return ProjectProgressPeriod::create($payload);
    }
}
