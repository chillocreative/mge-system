<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\SiteLog;
use App\Models\SiteLogMachinery;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteLogController extends Controller
{
    private const MACHINERY_TYPES = [
        'Excavator', 'Bulldozer', 'Crane', 'Compactor', 'Loader', 'Dump Truck', 'Generator', 'Other',
    ];

    private const WEATHER_CONDITIONS = ['rain_start', 'rain_stop', 'overcast', 'clear'];

    public function index(int $projectId, Request $request): JsonResponse
    {
        $logs = SiteLog::where('project_id', $projectId)
            ->with(['logger:id,first_name,last_name', 'machinery', 'weatherEvents'])
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
        $weatherEvents = $validated['weather_events'] ?? [];
        unset($validated['machinery'], $validated['weather_events']);

        $validated['project_id'] = $project->id;
        $validated['logged_by'] = $request->user()->id;

        $log = SiteLog::create($validated);
        $this->syncMachinery($log, $machinery);
        $this->syncWeatherEvents($log, $weatherEvents);

        return $this->created($log->load(['logger:id,first_name,last_name', 'machinery', 'weatherEvents']), 'Site log created.');
    }

    public function show(int $projectId, int $logId): JsonResponse
    {
        $log = SiteLog::where('project_id', $projectId)
            ->with(['logger:id,first_name,last_name', 'machinery', 'weatherEvents'])
            ->findOrFail($logId);

        return $this->success($log);
    }

    /**
     * A site log locks for editing a set number of days after its date
     * (plan 20.3), so a dated field record cannot be silently rewritten later.
     * Users with the elevated projects.delete permission may still edit a locked
     * log — for genuine corrections — and every such change is audited.
     */
    private function assertEditable(Request $request, SiteLog $log): void
    {
        $windowDays = (int) config('sitelogs.edit_window_days', 7);

        if ($windowDays <= 0 || $request->user()?->can('projects.delete')) {
            return;
        }

        $lockedAfter = \Illuminate\Support\Carbon::parse($log->log_date)->addDays($windowDays)->endOfDay();

        abort_if(
            now()->greaterThan($lockedAfter),
            422,
            "This site log is older than {$windowDays} days and is locked. Ask a project manager to make corrections.",
        );
    }

    private function auditLog(Request $request, SiteLog $log, string $action): void
    {
        ActivityLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => SiteLog::class,
            'subject_id' => $log->id,
            'properties' => ['project_id' => $log->project_id, 'log_date' => (string) $log->log_date],
        ]);
    }

    public function update(int $projectId, int $logId, Request $request): JsonResponse
    {
        $log = SiteLog::where('project_id', $projectId)->findOrFail($logId);

        $this->assertEditable($request, $log);

        $validated = $this->validatePayload($request, false);
        $machinery = $validated['machinery'] ?? null;
        $weatherEvents = $validated['weather_events'] ?? null;
        unset($validated['machinery'], $validated['weather_events']);

        $log->update($validated);
        if ($machinery !== null) {
            $this->syncMachinery($log, $machinery);
        }
        if ($weatherEvents !== null) {
            $this->syncWeatherEvents($log, $weatherEvents);
        }

        $this->auditLog($request, $log, 'sitelog.updated');

        return $this->success($log->fresh()->load(['logger:id,first_name,last_name', 'machinery', 'weatherEvents']), 'Site log updated.');
    }

    public function destroy(int $projectId, int $logId, Request $request): JsonResponse
    {
        $log = SiteLog::where('project_id', $projectId)->findOrFail($logId);

        $this->assertEditable($request, $log);
        $this->auditLog($request, $log, 'sitelog.deleted');
        $log->delete();

        return $this->success(null, 'Site log deleted.');
    }

    /**
     * Monthly machinery usage report for a project (Ciri 2), counted in DAYS.
     *
     * The critical rule (plan 2.3.1): count the number of DISTINCT dates each
     * machinery type appears on — not the number of rows and not the sum of
     * quantities. A machine logged seven times on one day is one day of use, not
     * seven. Utilisation is days-used over the number of distinct site-log days
     * in the month, which answers the question the boss asks next ("why was the
     * roller only used a third of the time?").
     */
    public function machineryReport(int $projectId, Request $request): JsonResponse
    {
        $request->validate(['month' => ['required', 'date_format:Y-m']]);

        $month = $request->string('month')->value();
        $from = $month.'-01';
        $to = date('Y-m-t', strtotime($from));

        $rows = SiteLogMachinery::query()
            ->join('site_logs', 'site_logs.id', '=', 'site_log_machinery.site_log_id')
            ->where('site_logs.project_id', $projectId)
            ->whereBetween('site_logs.log_date', [$from, $to])
            ->groupBy('site_log_machinery.machinery_type')
            ->selectRaw('site_log_machinery.machinery_type as type')
            ->selectRaw('COUNT(DISTINCT site_logs.log_date) as days_used')
            ->selectRaw('MIN(site_logs.log_date) as first_used')
            ->selectRaw('MAX(site_logs.log_date) as last_used')
            ->orderByDesc('days_used')
            ->get();

        // Distinct working days actually logged in the month — the utilisation base.
        $workingDays = (int) SiteLog::forProject($projectId)
            ->forPeriod($from, $to)
            ->distinct()
            ->count('log_date');

        $machinery = $rows->map(fn ($r) => [
            'type' => $r->type,
            'days_used' => (int) $r->days_used,
            'first_used' => $r->first_used,
            'last_used' => $r->last_used,
            'utilisation' => $workingDays > 0 ? round($r->days_used / $workingDays * 100) : 0,
        ]);

        return $this->success([
            'month' => $month,
            'working_days' => $workingDays,
            'machinery_count' => $machinery->count(),
            'total_machine_days' => (int) $machinery->sum('days_used'),
            'machinery' => $machinery,
        ]);
    }

    public function monthlyReportPdf(int $projectId, Request $request)
    {
        $request->validate(['month' => ['required', 'date_format:Y-m']]);

        $project = Project::findOrFail($projectId);
        $from = $request->string('month').'-01';
        $to = date('Y-m-t', strtotime($from));

        $logs = SiteLog::forProject($projectId)
            ->forPeriod($from, $to)
            ->with(['machinery', 'weatherEvents'])
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
        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(file_get_contents($path));
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

    private function syncWeatherEvents(SiteLog $log, array $weatherEvents): void
    {
        $log->weatherEvents()->delete();

        foreach ($weatherEvents as $item) {
            if (empty($item['condition']) || empty($item['event_time'])) {
                continue;
            }
            $log->weatherEvents()->create([
                'condition' => $item['condition'],
                'event_time' => $item['event_time'],
            ]);
        }
    }

    private function validatePayload(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $machineryTypes = implode(',', self::MACHINERY_TYPES);
        $weatherConditions = implode(',', self::WEATHER_CONDITIONS);

        return $request->validate([
            'log_date' => [$required, 'date'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'weather' => ['nullable', 'in:sunny,cloudy,rainy,stormy,windy,other'],
            'workers_count' => ['nullable', 'integer', 'min:0'],
            'work_performed' => ['nullable', 'string'],
            'materials_used' => ['nullable', 'string'],
            'equipment_used' => ['nullable', 'string'],
            'safety_notes' => ['nullable', 'string'],
            'issues' => ['nullable', 'string'],
            'machinery' => ['nullable', 'array'],
            'machinery.*.machinery_type' => ['required_with:machinery', 'in:'.$machineryTypes],
            'machinery.*.quantity' => ['nullable', 'integer', 'min:1'],
            'weather_events' => ['nullable', 'array'],
            'weather_events.*.condition' => ['required_with:weather_events', 'in:'.$weatherConditions],
            'weather_events.*.event_time' => ['required_with:weather_events', 'date_format:H:i'],
        ]);
    }
}
