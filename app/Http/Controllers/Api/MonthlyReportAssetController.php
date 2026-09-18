<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonthlyReport;
use App\Models\MonthlyReportAsset;
use App\Services\MonthlyReport\AssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MonthlyReportAssetController extends Controller
{
    public function __construct(private AssetService $service) {}

    public function index(MonthlyReport $report): JsonResponse
    {
        $assets = $report->assets()->orderBy('sort_order')->orderBy('id')->get()->map(fn (MonthlyReportAsset $asset) => $this->present($asset));

        return $this->success($assets);
    }

    private const CHART_KINDS = ['chart_physical_scurve', 'chart_financial_scurve'];

    public function store(MonthlyReport $report, Request $request): JsonResponse
    {
        $kind = $request->input('kind', 'gantt_page');
        $isChart = in_array($kind, self::CHART_KINDS, true);

        $validated = $request->validate([
            'file' => $isChart
                ? ['required', 'file', 'extensions:png', 'max:2048']
                : ['required', 'file', 'extensions:pdf,png,jpg,jpeg', 'max:20480'],
            'kind' => ['sometimes', Rule::in(['gantt_page', 'custom', ...self::CHART_KINDS])],
        ]);

        $asset = $this->service->store($report, $validated['file'], $validated['kind'] ?? 'gantt_page');

        return $this->created($this->present($asset));
    }

    public function update(MonthlyReport $report, int $asset, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sort_order' => ['required', 'integer'],
        ]);

        $model = $this->findAsset($report, $asset);
        $model = $this->service->reorder($model, $validated['sort_order']);

        return $this->success($this->present($model));
    }

    public function destroy(MonthlyReport $report, int $asset): JsonResponse
    {
        $model = $this->findAsset($report, $asset);
        $this->service->delete($model);

        return $this->success(null, 'Asset deleted.');
    }

    private function findAsset(MonthlyReport $report, int $assetId): MonthlyReportAsset
    {
        return MonthlyReportAsset::where('report_id', $report->id)->findOrFail($assetId);
    }

    private function present(MonthlyReportAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'kind' => $asset->kind,
            'file_path' => $asset->file_path,
            'file_name' => $asset->file_name,
            'extension' => $asset->extension,
            'size' => $asset->size,
            'pages' => $asset->pages,
            'sort_order' => $asset->sort_order,
            'created_at' => $asset->created_at,
        ];
    }
}
