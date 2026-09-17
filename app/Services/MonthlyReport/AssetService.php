<?php

namespace App\Services\MonthlyReport;

use App\Models\MonthlyReport;
use App\Models\MonthlyReportAsset;
use App\Services\MonthlyReport\Export\PdfMerger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AssetService
{
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

        $nextSortOrder = (int) MonthlyReportAsset::where('report_id', $report->id)->max('sort_order') + 1;

        return MonthlyReportAsset::create([
            'report_id' => $report->id,
            'kind' => $kind,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'extension' => $ext,
            'size' => $file->getSize(),
            'pages' => $pages,
            'sort_order' => $nextSortOrder,
        ]);
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
