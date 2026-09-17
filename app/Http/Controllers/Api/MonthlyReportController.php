<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonthlyReport;
use App\Services\MonthlyReport\Export\PdfExporter;
use App\Services\MonthlyReport\MonthlyReportService;
use App\Services\MonthlyReport\SectionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MonthlyReportController extends Controller
{
    public function __construct(private MonthlyReportService $service, private PdfExporter $pdfExporter) {}

    public function index(Request $request): JsonResponse
    {
        $query = MonthlyReport::query()->with(['project:id,name,code', 'period']);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return $this->success($query->orderByDesc('id')->paginate(min(100, max(1, $request->integer('per_page', 15)))));
    }

    public function indexForProject(int $projectId, Request $request): JsonResponse
    {
        $query = MonthlyReport::query()->with(['project:id,name,code', 'period'])->where('project_id', $projectId);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return $this->success($query->orderByDesc('id')->paginate(min(100, max(1, $request->integer('per_page', 15)))));
    }

    public function store(int $projectId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_no' => ['nullable', 'integer', 'min:1', 'max:65535', Rule::unique('monthly_reports', 'report_no')->where('project_id', $projectId)],
            'period_id' => ['nullable', 'integer', 'exists:project_progress_periods,id'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['required_without:period_id', 'date'],
            'month_label' => ['nullable', 'string', 'max:40'],
            'evaluation_date' => ['nullable', 'date'],
            'copy_from_report_id' => ['nullable', 'integer', Rule::exists('monthly_reports', 'id')->where('project_id', $projectId)],
        ]);

        $report = $this->service->create($projectId, $validated, $request->user()->id);

        return $this->created($this->present($report), 'Monthly report created.');
    }

    public function show(MonthlyReport $report): JsonResponse
    {
        $report->load(['sections', 'project:id,name,code', 'period']);

        return $this->success($this->present($report));
    }

    public function update(MonthlyReport $report, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'month_label' => ['sometimes', 'string', 'max:40'],
            'evaluation_date' => ['sometimes', 'nullable', 'date'],
            'signatories' => ['sometimes', 'nullable', 'array'],
            'options' => ['sometimes', 'nullable', 'array'],
            'options.landscape_sections' => ['sometimes', 'nullable', 'array'],
            'options.landscape_sections.*' => ['string', Rule::in(array_diff(array_keys(SectionRegistry::all()), ['cover']))],
        ]);

        $report = $this->service->update($report, $validated);

        return $this->success($this->present($report), 'Monthly report updated.');
    }

    public function saveSection(MonthlyReport $report, string $key, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'overrides' => ['sometimes', 'nullable', 'array'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'include' => ['sometimes', 'boolean'],
        ]);

        $section = $this->service->saveSection($report, $key, $validated);

        return $this->success($section, 'Section saved.');
    }

    public function regenerate(MonthlyReport $report, Request $request): JsonResponse
    {
        $report = $this->service->regenerate($report, $request->query('key'));

        return $this->success($this->present($report), 'Report regenerated.');
    }

    public function finalise(MonthlyReport $report, Request $request): JsonResponse
    {
        $report = $this->service->finalise($report, $request->user()->id);

        return $this->success($this->present($report), 'Report finalised.');
    }

    public function reopen(MonthlyReport $report): JsonResponse
    {
        $report = $this->service->reopen($report);

        return $this->success($this->present($report), 'Report reopened.');
    }

    public function exportPdf(int $reportId)
    {
        set_time_limit(120);
        ini_set('memory_limit', '512M');

        $report = MonthlyReport::with(['sections', 'project', 'period'])->findOrFail($reportId);
        $bytes = $this->pdfExporter->render($report);

        $projectCode = preg_replace('/[^A-Za-z0-9]+/', '-', (string) $report->project?->code) ?: 'project';
        $filename = "MPR-{$projectCode}-No{$report->report_no}.pdf";

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function destroy(MonthlyReport $report): JsonResponse
    {
        $this->service->destroy($report);

        return $this->success(null, 'Monthly report deleted.');
    }

    private function present(MonthlyReport $report): array
    {
        return [
            ...$report->toArray(),
            'project' => $report->project ? ['id' => $report->project->id, 'name' => $report->project->name, 'code' => $report->project->code] : null,
            'period' => $report->period,
            'sections' => $report->sections->map(fn ($s) => [
                'id' => $s->id,
                'key' => $s->key,
                'title' => $s->title,
                'sort_order' => $s->sort_order,
                'include' => $s->include,
                'data' => $s->data,
                'overrides' => $s->overrides,
                'notes' => $s->notes,
                'merged' => $s->merged,
                'regenerated_at' => $s->regenerated_at,
            ]),
        ];
    }
}
