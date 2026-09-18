<?php

namespace App\Services\ReportData;

use App\Models\ProgrammeActivity;
use App\Models\Project;
use App\Models\ProjectProgrammeVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProgrammeService
{
    /**
     * @param  array{label: string, status_date?: ?string, source_type: string, source_file_path?: ?string, source_file_name?: ?string, set_current?: bool}  $meta
     * @param  array<int, array{name: string, outline_level: int, duration_days?: ?int, start?: ?string, finish?: ?string, actual_pct?: ?float, plan_pct?: ?float, is_summary?: bool}>  $activities
     */
    public function createVersion(int $projectId, array $meta, array $activities, ?int $userId): ProjectProgrammeVersion
    {
        $count = count($activities);

        if ($count === 0) {
            throw ValidationException::withMessages(['activities' => 'At least one activity is required.']);
        }

        if ($count > 2000) {
            throw ValidationException::withMessages(['activities' => 'A programme cannot contain more than 2000 activities.']);
        }

        $setCurrent = $meta['set_current'] ?? true;

        return DB::transaction(function () use ($projectId, $meta, $activities, $userId, $count, $setCurrent) {
            Project::whereKey($projectId)->lockForUpdate()->first();

            if ($setCurrent) {
                $siblingIds = ProjectProgrammeVersion::where('project_id', $projectId)
                    ->lockForUpdate()
                    ->pluck('id');

                ProjectProgrammeVersion::whereIn('id', $siblingIds)->update(['is_current' => false]);
            }

            $version = ProjectProgrammeVersion::create([
                'project_id' => $projectId,
                'label' => $meta['label'],
                'status_date' => $meta['status_date'] ?? null,
                'source_type' => $meta['source_type'],
                'source_file_path' => $meta['source_file_path'] ?? null,
                'source_file_name' => $meta['source_file_name'] ?? null,
                'is_current' => $setCurrent,
                'activity_count' => $count,
                'imported_by' => $userId,
            ]);

            $rows = [];
            foreach (array_values($activities) as $i => $activity) {
                $rows[] = [
                    'version_id' => $version->id,
                    'seq' => $i + 1,
                    'outline_level' => max(1, (int) ($activity['outline_level'] ?? 1)),
                    'name' => $activity['name'],
                    'duration_days' => $activity['duration_days'] ?? null,
                    'start' => $activity['start'] ?? null,
                    'finish' => $activity['finish'] ?? null,
                    'actual_pct' => $activity['actual_pct'] ?? null,
                    'plan_pct' => $activity['plan_pct'] ?? null,
                    'is_summary' => $activity['is_summary'] ?? false,
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                ProgrammeActivity::insert($chunk);
            }

            return $version;
        });
    }

    public function setCurrent(ProjectProgrammeVersion $version): void
    {
        DB::transaction(function () use ($version) {
            Project::whereKey($version->project_id)->lockForUpdate()->first();

            $siblingIds = ProjectProgrammeVersion::where('project_id', $version->project_id)
                ->lockForUpdate()
                ->pluck('id');

            ProjectProgrammeVersion::whereIn('id', $siblingIds)->update(['is_current' => false]);
            $version->update(['is_current' => true]);
        });
    }

    /** @param  array{label?: string, status_date?: ?string}  $attrs */
    public function update(ProjectProgrammeVersion $version, array $attrs): ProjectProgrammeVersion
    {
        $version->update(array_intersect_key($attrs, array_flip(['label', 'status_date'])));

        return $version;
    }

    public function delete(ProjectProgrammeVersion $version): void
    {
        $projectId = $version->project_id;
        $wasCurrent = $version->is_current;
        $filePath = $version->source_file_path;

        DB::transaction(function () use ($version, $projectId, $wasCurrent) {
            $version->delete();

            if ($wasCurrent) {
                $latest = ProjectProgrammeVersion::where('project_id', $projectId)
                    ->orderByDesc('status_date')
                    ->orderByDesc('id')
                    ->first();

                $latest?->update(['is_current' => true]);
            }
        });

        if ($filePath) {
            Storage::disk('local')->delete($filePath);
        }
    }

    public function currentFor(int $projectId): ?ProjectProgrammeVersion
    {
        return ProjectProgrammeVersion::where('project_id', $projectId)->where('is_current', true)->first()
            ?? ProjectProgrammeVersion::where('project_id', $projectId)
                ->orderByDesc('status_date')
                ->orderByDesc('id')
                ->first();
    }
}
