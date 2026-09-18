<?php

namespace App\Http\Controllers\Api\ReportData;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectProgrammeVersion;
use App\Services\ReportData\Programme\ProgrammeMspdiImporter;
use App\Services\ReportData\Programme\ProgrammeXlsxImporter;
use App\Services\ReportData\ProgrammeService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProgrammeVersionController extends Controller
{
    /** Directory prefix (relative to the `local` disk) under which programme files are stored, per project. */
    private function dir(int $projectId): string
    {
        return "programmes/{$projectId}";
    }

    public function __construct(private readonly ProgrammeService $programmeService) {}

    public function index(int $projectId): JsonResponse
    {
        $versions = ProjectProgrammeVersion::where('project_id', $projectId)
            ->with('importer')
            ->orderByDesc('status_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ProjectProgrammeVersion $v) => [
                'id' => $v->id,
                'label' => $v->label,
                'status_date' => $v->status_date?->format('Y-m-d'),
                'source_type' => $v->source_type,
                'source_file_name' => $v->source_file_name,
                'is_current' => $v->is_current,
                'activity_count' => $v->activity_count,
                'imported_by_name' => $v->importer?->full_name,
                'created_at' => $v->created_at,
            ]);

        return $this->success($versions);
    }

    public function preview(int $projectId, Request $request): JsonResponse
    {
        Project::findOrFail($projectId);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:xlsx,xls,csv'],
        ]);

        $file = $validated['file'];
        $ext = strtolower($file->getClientOriginalExtension());
        $relativePath = $file->storeAs($this->dir($projectId), 'tmp-'.Str::uuid().'.'.$ext, 'local');

        $preview = (new ProgrammeXlsxImporter)->preview(Storage::disk('local')->path($relativePath));

        return $this->success($preview + [
            'token' => Crypt::encryptString($relativePath),
            'file_name' => $file->getClientOriginalName(),
        ]);
    }

    public function import(int $projectId, Request $request): JsonResponse
    {
        Project::findOrFail($projectId);

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'mapping' => ['required', 'array'],
            'mapping.name' => ['required', 'integer', 'min:0'],
            'mapping.duration' => ['nullable', 'integer', 'min:0'],
            'mapping.start' => ['nullable', 'integer', 'min:0'],
            'mapping.finish' => ['nullable', 'integer', 'min:0'],
            'mapping.actual_pct' => ['nullable', 'integer', 'min:0'],
            'mapping.plan_pct' => ['nullable', 'integer', 'min:0'],
            'mapping.outline_level' => ['nullable', 'integer', 'min:0'],
            'label' => ['required', 'string', 'max:120'],
            'status_date' => ['nullable', 'date'],
            'set_current' => ['nullable', 'boolean'],
            'file_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $relativePath = Crypt::decryptString($validated['token']);
        } catch (DecryptException) {
            return $this->error('The upload token is invalid or has expired. Please re-upload the file.', 422);
        }

        if (! str_starts_with($relativePath, $this->dir($projectId).'/tmp-')
            || ! Storage::disk('local')->exists($relativePath)) {
            return $this->error('The upload token is invalid or has expired. Please re-upload the file.', 422);
        }

        try {
            $activities = (new ProgrammeXlsxImporter)->import(Storage::disk('local')->path($relativePath), $validated['mapping']);
        } catch (ValidationException $e) {
            Storage::disk('local')->delete($relativePath);
            throw $e;
        }

        $ext = pathinfo($relativePath, PATHINFO_EXTENSION);
        $newPath = $this->dir($projectId).'/'.Str::uuid().'.'.$ext;

        try {
            $version = $this->programmeService->createVersion($projectId, [
                'label' => $validated['label'],
                'status_date' => $validated['status_date'] ?? null,
                'source_type' => 'xlsx',
                'source_file_path' => $newPath,
                'source_file_name' => $validated['file_name'] ?? basename($relativePath),
                'set_current' => $validated['set_current'] ?? true,
            ], $activities, $request->user()->id);
        } catch (ValidationException $e) {
            Storage::disk('local')->delete($relativePath);
            throw $e;
        }

        Storage::disk('local')->move($relativePath, $newPath);

        return $this->created($version, 'Programme imported.');
    }

    public function importMspdi(int $projectId, Request $request): JsonResponse
    {
        Project::findOrFail($projectId);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'extensions:xml'],
            'label' => ['required', 'string', 'max:120'],
            'status_date' => ['nullable', 'date'],
            'set_current' => ['nullable', 'boolean'],
        ]);

        $file = $validated['file'];
        $uuid = (string) Str::uuid();
        $tmpPath = $file->storeAs($this->dir($projectId), 'tmp-'.$uuid.'.xml', 'local');

        try {
            $activities = (new ProgrammeMspdiImporter)->import(Storage::disk('local')->path($tmpPath));
        } catch (ValidationException $e) {
            Storage::disk('local')->delete($tmpPath);
            throw $e;
        }

        $newPath = $this->dir($projectId).'/'.$uuid.'.xml';
        Storage::disk('local')->move($tmpPath, $newPath);

        $version = $this->programmeService->createVersion($projectId, [
            'label' => $validated['label'],
            'status_date' => $validated['status_date'] ?? null,
            'source_type' => 'mspdi',
            'source_file_path' => $newPath,
            'source_file_name' => $file->getClientOriginalName(),
            'set_current' => $validated['set_current'] ?? true,
        ], $activities, $request->user()->id);

        return $this->created($version, 'Programme imported.');
    }

    public function update(int $projectId, int $versionId, Request $request): JsonResponse
    {
        $version = ProjectProgrammeVersion::where('project_id', $projectId)->findOrFail($versionId);

        $validated = $request->validate([
            'label' => ['sometimes', 'string', 'max:120'],
            'status_date' => ['sometimes', 'nullable', 'date'],
            'is_current' => ['sometimes', 'accepted'],
        ]);

        if (($validated['is_current'] ?? null) !== null) {
            $this->programmeService->setCurrent($version);
        }

        $this->programmeService->update($version, $validated);

        return $this->success($version->fresh(), 'Programme version updated.');
    }

    public function destroy(int $projectId, int $versionId): JsonResponse
    {
        $version = ProjectProgrammeVersion::where('project_id', $projectId)->findOrFail($versionId);

        $this->programmeService->delete($version);

        return $this->success(null, 'Programme version removed.');
    }

    public function activities(int $projectId, int $versionId, Request $request): JsonResponse
    {
        $version = ProjectProgrammeVersion::where('project_id', $projectId)->findOrFail($versionId);

        $perPage = min(500, max(1, (int) $request->integer('per_page', 100)));

        $activities = $version->activities()
            ->orderBy('seq')
            ->paginate($perPage)
            ->through(fn ($a) => [
                'seq' => $a->seq,
                'outline_level' => $a->outline_level,
                'name' => $a->name,
                'duration_days' => $a->duration_days,
                'start' => $a->start?->format('Y-m-d'),
                'finish' => $a->finish?->format('Y-m-d'),
                'actual_pct' => $a->actual_pct,
                'plan_pct' => $a->plan_pct,
                'is_summary' => $a->is_summary,
            ]);

        return $this->success($activities);
    }
}
