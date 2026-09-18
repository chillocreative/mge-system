<?php

namespace App\Services\MonthlyReport;

use App\Models\MonthlyReport;
use App\Models\MonthlyReportAsset;
use App\Services\MonthlyReport\Export\PdfMerger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AssetService
{
    private const CHART_KINDS = ['chart_physical_scurve', 'chart_financial_scurve'];

    public function __construct(private PdfMerger $merger) {}

    public function store(MonthlyReport $report, UploadedFile $file, string $kind): MonthlyReportAsset
    {
        $this->guardNotFinal($report);

        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs("monthly-reports/{$report->id}/assets", Str::uuid().'.'.$ext, 'local');

        $pages = 1;
        if ($ext === 'pdf') {
            try {
                $pages = $this->merger->probe(Storage::disk('local')->path($path));
            } catch (\RuntimeException $e) {
                Storage::disk('local')->delete($path);
                throw ValidationException::withMessages(['file' => $e->getMessage()]);
            }
        }

        try {
            return DB::transaction(function () use ($report, $kind, $path, $file, $ext, $pages) {
                $isChart = in_array($kind, self::CHART_KINDS, true);

                if ($isChart) {
                    // Chart snapshots replace in place: one asset per kind per report.
                    $previous = MonthlyReportAsset::where('report_id', $report->id)->where('kind', $kind)->first();
                    if ($previous) {
                        Storage::disk('local')->delete($previous->file_path);
                        $previous->delete();
                    }

                    $sortOrder = 0;
                } else {
                    $sortOrder = (int) MonthlyReportAsset::where('report_id', $report->id)->max('sort_order') + 1;

                    // Gantt pages are appended right after section 2.5, so make sure that
                    // section is part of the export instead of falling to the end of the PDF.
                    if ($kind === 'gantt_page') {
                        $report->sections()->where('key', '2.5')->update(['include' => true]);
                    }
                }

                return MonthlyReportAsset::create([
                    'report_id' => $report->id,
                    'kind' => $kind,
                    'file_path' => $path,
                    'file_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                    'extension' => $ext,
                    'size' => $file->getSize(),
                    'pages' => $pages,
                    'sort_order' => $sortOrder,
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function reorder(MonthlyReportAsset $asset, int $sortOrder): MonthlyReportAsset
    {
        $this->guardNotFinal($asset->report);

        $asset->update(['sort_order' => $sortOrder]);

        return $asset->fresh();
    }

    public function delete(MonthlyReportAsset $asset): void
    {
        $this->guardNotFinal($asset->report);

        Storage::disk('local')->delete($asset->file_path);
        $asset->delete();
    }

    private function guardNotFinal(MonthlyReport $report): void
    {
        if ($report->isFinal()) {
            throw ValidationException::withMessages(['report' => 'Finalised reports cannot be changed.']);
        }
    }
}
