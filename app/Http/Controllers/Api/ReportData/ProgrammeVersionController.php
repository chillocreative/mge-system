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
use Throwable;

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
        Project::findOrFail($projectId);

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

        $this->sweepStaleTmpFiles($projectId);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:xlsx,xls,csv'],
        ]);

        $file = $validated['file'];
        $ext = strtolower($file->getClientOriginalExtension());
        $relativePath = $file->storeAs($this->dir($projectId), 'tmp-'.Str::uuid().'.'.$ext, 'local');
        $fileName = $file->getClientOriginalName();

        $preview = (new ProgrammeXlsxImporter)->preview(Storage::disk('local')->path($relativePath));

        return $this->success($preview + [
            'token' => Crypt::encryptString(json_encode([
                'path' => $relativePath,
                'file_name' => $fileName,
                'issued_at' => now()->timestamp,
            ], JSON_INVALID_UTF8_SUBSTITUTE)),
            'file_name' => $fileName,
        ]);
    }

    /** Delete tmp-* uploads older than 24h in the project's programme directory. */
    private function sweepStaleTmpFiles(int $projectId): void
    {
        $dir = $this->dir($projectId);
        $cutoff = now()->subDay()->timestamp;

        foreach (Storage::disk('local')->files($dir) as $path) {
            if (! str_starts_with(basename($path), 'tmp-')) {
                continue;
            }

            try {
                if (Storage::disk('local')->lastModified($path) < $cutoff) {
                    Storage::disk('local')->delete($path);
                }
            } catch (Throwable) {
                continue;
            }
        }
    }

    /**
     * Decrypt and validate a preview token, returning the tmp file's relative path.
     *
     * @return array{path: string, file_name: ?string}|JsonResponse
     */
    private function decodeToken(string $token, int $projectId): array|JsonResponse
    {
        $invalid = fn () => $this->error('The upload token is invalid or has expired. Please re-upload the file.', 422);

        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return $invalid();
        }

        if (! is_array($payload)
            || ! is_string($payload['path'] ?? null)
            || ! is_int($payload['issued_at'] ?? null)) {
            return $this->error('The preview token is invalid — upload the file again.', 422);
        }

        if ($payload['issued_at'] < now()->subDay()->timestamp) {
            return $this->error('The preview has expired — upload the file again.', 422);
        }

        $relativePath = $payload['path'];

        if (! str_starts_with($relativePath, $this->dir($projectId).'/tmp-')
            || ! Storage::disk('local')->exists($relativePath)) {
            return $invalid();
        }

        return ['path' => $relativePath, 'file_name' => $payload['file_name'] ?? null];
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

        $decoded = $this->decodeToken($validated['token'], $projectId);

        if ($decoded instanceof JsonResponse) {
            return $decoded;
        }

        $relativePath = $decoded['path'];
        $tokenFileName = $decoded['file_name'];

        try {
            $activities = (new ProgrammeXlsxImporter)->import(Storage::disk('local')->path($relativePath), $validated['mapping']);
        } catch (ValidationException $e) {
            Storage::disk('local')->delete($relativePath);
            throw $e;
        }

        $ext = pathinfo($relativePath, PATHINFO_EXTENSION);
        $newPath = $this->dir($projectId).'/'.Str::uuid().'.'.$ext;

        // Move the upload to its permanent path first so createVersion() never has to
        // reach back into a tmp-* file; on any failure we clean up whatever landed on disk.
        Storage::disk('local')->move($relativePath, $newPath);

        try {
            $version = $this->programmeService->createVersion($projectId, [
                'label' => $validated['label'],
                'status_date' => $validated['status_date'] ?? null,
                'source_type' => 'xlsx',
                'source_file_path' => $newPath,
                'source_file_name' => $validated['file_name'] ?? $tokenFileName ?? basename($relativePath),
                'set_current' => $validated['set_current'] ?? true,
            ], $activities, $request->user()->id);
        } catch (Throwable $e) {
            Storage::disk('local')->delete($newPath);
            Storage::disk('local')->delete($relativePath);
            throw $e;
        }

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

        // Move the upload to its permanent path first; on any failure we clean up
        // whatever landed on disk (mirrors import()).
        Storage::disk('local')->move($tmpPath, $newPath);

        try {
            $version = $this->programmeService->createVersion($projectId, [
                'label' => $validated['label'],
                'status_date' => $validated['status_date'] ?? null,
                'source_type' => 'mspdi',
                'source_file_path' => $newPath,
                'source_file_name' => $file->getClientOriginalName(),
                'set_current' => $validated['set_current'] ?? true,
            ], $activities, $request->user()->id);
        } catch (Throwable $e) {
            Storage::disk('local')->delete($newPath);
            Storage::disk('local')->delete($tmpPath);
            throw $e;
        }

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
        Project::findOrFail($projectId);

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
