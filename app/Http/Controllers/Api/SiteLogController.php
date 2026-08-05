<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\SiteLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteLogController extends Controller
{
    private const MACHINERY_TYPES = [
        'Excavator', 'Bulldozer', 'Crane', 'Compactor', 'Loader', 'Dump Truck', 'Generator', 'Other',
    ];

    public function index(int $projectId, Request $request): JsonResponse
    {
        $logs = SiteLog::where('project_id', $projectId)
            ->with(['logger:id,first_name,last_name', 'machinery'])
            ->when($request->date_from && $request->date_to, fn ($q) => $q->forPeriod($request->date_from, $request->date_to))
            ->orderByDesc('log_date')
            ->paginate($request->integer('per_page', 15));

        return $this->success($logs);
    }

    public function store(int $projectId, Request $request): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        $validated = $this->validatePayload($request, true);
        $machinery = $validated['machinery'] ?? [];
        unset($validated['machinery']);

        $validated['project_id'] = $project->id;
        $validated['logged_by'] = $request->user()->id;

        $log = SiteLog::create($validated);
        $this->syncMachinery($log, $machinery);

        return $this->created($log->load(['logger:id,first_name,last_name', 'machinery']), 'Site log created.');
    }

    public function show(int $projectId, int $logId): JsonResponse
    {
        $log = SiteLog::where('project_id', $projectId)
            ->with(['logger:id,first_name,last_name', 'machinery'])
            ->findOrFail($logId);

        return $this->success($log);
    }

    public function update(int $projectId, int $logId, Request $request): JsonResponse
    {
        $log = SiteLog::where('project_id', $projectId)->findOrFail($logId);

        $validated = $this->validatePayload($request, false);
        $machinery = $validated['machinery'] ?? null;
        unset($validated['machinery']);

        $log->update($validated);
        if ($machinery !== null) {
            $this->syncMachinery($log, $machinery);
        }

        return $this->success($log->fresh()->load(['logger:id,first_name,last_name', 'machinery']), 'Site log updated.');
    }

    public function destroy(int $projectId, int $logId): JsonResponse
    {
        $log = SiteLog::where('project_id', $projectId)->findOrFail($logId);
        $log->delete();

        return $this->success(null, 'Site log deleted.');
    }

    public function monthlyReportPdf(int $projectId, Request $request)
    {
        $request->validate(['month' => ['required', 'date_format:Y-m']]);

        $project = Project::findOrFail($projectId);
        $from = $request->string('month') . '-01';
        $to = date('Y-m-t', strtotime($from));

        $logs = SiteLog::forProject($projectId)
            ->forPeriod($from, $to)
            ->with('machinery')
            ->orderBy('log_date')
            ->get();

        $totalWorkers = $logs->sum('workers_count');
        $machineryTotals = $logs->flatMap->machinery
            ->groupBy('machinery_type')
            ->map(fn ($rows) => $rows->sum('quantity'))
            ->sortDesc();

        $pdf = Pdf::loadView('pdf.site-log-monthly-report', [
            'project' => $project,
            'month' => $request->string('month'),
            'logs' => $logs,
            'totalWorkers' => $totalWorkers,
            'machineryTotals' => $machineryTotals,
            'company' => config('payroll.company'),
            'logo' => $this->logoData(),
        ])->setPaper('a4');

        return $pdf->download("site-log-report-{$project->code}-{$request->string('month')}.pdf");
    }

    private function logoData(): ?string
    {
        $path = public_path('logo.png');
        if (!is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
    }

    private function syncMachinery(SiteLog $log, array $machinery): void
    {
        $log->machinery()->delete();

        foreach ($machinery as $item) {
            if (empty($item['machinery_type'])) {
                continue;
            }
            $log->machinery()->create([
                'machinery_type' => $item['machinery_type'],
                'quantity' => $item['quantity'] ?? 1,
            ]);
        }
    }

    private function validatePayload(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $types = implode(',', self::MACHINERY_TYPES);

        return $request->validate([
            'log_date' => [$required, 'date'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'weather' => ['nullable', 'in:sunny,cloudy,rainy,stormy,windy,other'],
            'rain_start_time' => ['nullable', 'date_format:H:i'],
            'rain_end_time' => ['nullable', 'date_format:H:i'],
            'overcast_time' => ['nullable', 'date_format:H:i'],
            'clear_time' => ['nullable', 'date_format:H:i'],
            'workers_count' => ['nullable', 'integer', 'min:0'],
            'work_performed' => ['nullable', 'string'],
            'materials_used' => ['nullable', 'string'],
            'equipment_used' => ['nullable', 'string'],
            'safety_notes' => ['nullable', 'string'],
            'issues' => ['nullable', 'string'],
            'machinery' => ['nullable', 'array'],
            'machinery.*.machinery_type' => ['required_with:machinery', 'in:' . $types],
            'machinery.*.quantity' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}
