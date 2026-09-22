<?php

namespace App\Services\Environment;

use App\Models\EnvironmentReport;
use App\Models\EnvironmentReportAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class EnvironmentReportService
{
    public function __construct(private EnvironmentSectionDefaults $defaults) {}

    public function list(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);

        $query = EnvironmentReport::with(['project:id,name,code'])
            ->orderByDesc('report_no')
            ->orderByDesc('id');

        if (! empty($filters['project_id'])) {
            $query->forProject((int) $filters['project_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(fn ($q) => $q->where('title', 'like', "%{$term}%"));
        }

        return $query->paginate($perPage);
    }

    public function create(array $data, int $userId): EnvironmentReport
    {
        return DB::transaction(function () use ($data, $userId) {
            $reportNo = ((int) EnvironmentReport::where('project_id', $data['project_id'])
                ->withTrashed()
                ->lockForUpdate()
                ->max('report_no')) + 1;

            $report = EnvironmentReport::create([
                'project_id' => $data['project_id'],
                'report_no' => $reportNo,
                'title' => $data['title'] ?? "MONTHLY ENVIRONMENT REPORT NO.{$reportNo}",
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'status' => 'draft',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $report->sections = $this->defaults->build($report);
            $report->signatories = $this->defaults->signatories($report);
            $report->generated_at = now();
            $report->save();

            return $report->load(['project:id,name,code', 'creator', 'updater']);
        });
    }

    public function update(EnvironmentReport $report, array $data, int $userId): EnvironmentReport
    {
        $this->guardNotFinal($report);

        if ($report->status === 'finalised') {
            abort(422, 'Report is finalised');
        }

        if (array_key_exists('title', $data)) {
            $report->title = $data['title'];
        }
        if (array_key_exists('period_start', $data)) {
            $report->period_start = $data['period_start'];
        }
        if (array_key_exists('period_end', $data)) {
            $report->period_end = $data['period_end'];
        }
        if (array_key_exists('signatories', $data)) {
            $report->signatories = $data['signatories'];
        }
        if (array_key_exists('sections', $data)) {
            $sections = $report->sections ?? [];
            foreach ($data['sections'] as $key => $value) {
                $sections[$key] = $value;
            }
            $report->sections = $sections;
        }

        $report->updated_by = $userId;
        $report->save();

        return $report->load(['project:id,name,code', 'creator', 'updater']);
    }

    public function regenerate(EnvironmentReport $report, string $key): EnvironmentReport
    {
        $this->guardNotFinal($report);

        if ($report->status === 'finalised') {
            abort(422, 'Report is finalised');
        }

        $sections = $report->sections ?? [];
        $sections[$key] = $this->defaults->buildOne($report, $key);
        $report->sections = $sections;
        $report->save();

        return $report->load(['project:id,name,code', 'creator', 'updater']);
    }

    public function finalise(EnvironmentReport $report, int $userId): EnvironmentReport
    {
        $report->status = 'finalised';
        $report->finalised_at = now();
        $report->finalised_by = $userId;
        $report->save();

        return $report->load(['project:id,name,code', 'creator', 'updater']);
    }

    public function reopen(EnvironmentReport $report, int $userId): EnvironmentReport
    {
        $report->status = 'draft';
        $report->finalised_at = null;
        $report->finalised_by = null;
        $report->updated_by = $userId;
        $report->save();

        return $report->load(['project:id,name,code', 'creator', 'updater']);
    }

    public function delete(EnvironmentReport $report): void
    {
        $this->guardNotFinal($report);

        foreach ($report->assets as $asset) {
            Storage::disk('local')->delete($asset->file_path);
        }

        $report->delete();
    }

    // ── Assets ──

    public function addAsset(EnvironmentReport $report, string $kind, UploadedFile $file, ?string $caption): EnvironmentReportAsset
    {
        $this->guardNotFinal($report);

        $path = $file->store("projects/environment/reports/{$report->id}", 'local');

        $maxSort = $report->assets()->where('kind', $kind)->max('sort_order');

        return EnvironmentReportAsset::create([
            'environment_report_id' => $report->id,
            'kind' => $kind,
            'caption' => $caption,
            'sort_order' => ($maxSort ?? -1) + 1,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientOriginalExtension(),
            'file_size' => $file->getSize(),
        ]);
    }

    public function updateAsset(EnvironmentReportAsset $asset, ?string $caption, ?int $sortOrder): EnvironmentReportAsset
    {
        $this->guardNotFinal($asset->report);

        if ($caption !== null) {
            $asset->caption = $caption;
        }
        if ($sortOrder !== null) {
            $asset->sort_order = $sortOrder;
        }
        $asset->save();

        return $asset;
    }

    public function removeAsset(EnvironmentReportAsset $asset): void
    {
        $this->guardNotFinal($asset->report);

        Storage::disk('local')->delete($asset->file_path);
        $asset->delete();
    }

    private function guardNotFinal(EnvironmentReport $report): void
    {
        if ($report->status === 'finalised') {
            throw ValidationException::withMessages(['report' => 'Finalised reports cannot be changed.']);
        }
    }

    /** Sanitised export filename (without extension) — project codes may contain slashes. */
    public function exportFilename(EnvironmentReport $report): string
    {
        $projectCode = $report->project?->code ?: 'project';
        $sanitised = preg_replace('/[^A-Za-z0-9._-]+/', '-', $projectCode);

        return trim($sanitised, '-')."-Environment-Report-No{$report->report_no}";
    }
}
