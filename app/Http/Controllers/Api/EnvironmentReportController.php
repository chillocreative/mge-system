<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnvironmentReport;
use App\Models\EnvironmentReportAsset;
use App\Services\Environment\EnvironmentReportService;
use App\Services\Environment\Export\EnvironmentReportDocxExporter;
use App\Services\Environment\Export\EnvironmentReportPdfExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EnvironmentReportController extends Controller
{
    public function __construct(
        private EnvironmentReportService $service,
        private EnvironmentReportPdfExporter $pdfExporter,
        private EnvironmentReportDocxExporter $docxExporter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['project_id', 'status', 'search', 'per_page']);

        return $this->success($this->service->list($filters));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $report = $this->service->create($data, $request->user()->id);

        return $this->created($report, 'Environment report created.');
    }

    public function show(int $id): JsonResponse
    {
        $report = EnvironmentReport::with(['assets', 'project:id,name,code', 'creator'])->findOrFail($id);

        return $this->success($report);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $report = EnvironmentReport::findOrFail($id);

        $data = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'period_start' => ['sometimes', 'required', 'date'],
            'period_end' => ['sometimes', 'required', 'date', 'after_or_equal:period_start'],
            'signatories' => ['sometimes', 'nullable', 'array'],
            'signatories.*.slot' => ['required_with:signatories', 'string', 'in:prepared,verified,accepted'],
            'signatories.*.name' => ['nullable', 'string', 'max:255'],
            'signatories.*.designation' => ['nullable', 'string', 'max:255'],
            'signatories.*.company' => ['nullable', 'string', 'max:255'],
            'sections' => ['sometimes', 'nullable', 'array'],
        ]);

        if (array_key_exists('sections', $data) && $data['sections']) {
            $invalid = array_diff(array_keys($data['sections']), EnvironmentReport::SECTION_KEYS);
            if ($invalid !== []) {
                return $this->error('Invalid section key(s): '.implode(', ', $invalid), 422);
            }
        }

        $report = $this->service->update($report, $data, $request->user()->id);

        return $this->success($report, 'Environment report updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $report = EnvironmentReport::findOrFail($id);
        $this->service->delete($report);

        return $this->success(null, 'Environment report deleted.');
    }

    public function regenerate(Request $request, int $id, string $section): JsonResponse
    {
        $request->validate([]);

        if (! in_array($section, EnvironmentReport::SECTION_KEYS, true)) {
            return $this->error('Invalid section.', 422);
        }

        $report = EnvironmentReport::findOrFail($id);
        $report = $this->service->regenerate($report, $section);

        return $this->success($report, 'Section regenerated.');
    }

    public function finalise(Request $request, int $id): JsonResponse
    {
        $report = EnvironmentReport::findOrFail($id);
        $report = $this->service->finalise($report, $request->user()->id);

        return $this->success($report, 'Report finalised.');
    }

    public function reopen(Request $request, int $id): JsonResponse
    {
        $report = EnvironmentReport::findOrFail($id);
        $report = $this->service->reopen($report, $request->user()->id);

        return $this->success($report, 'Report reopened.');
    }

    // ── Export ──

    public function exportPdf(Request $request, int $id)
    {
        $report = EnvironmentReport::with(['assets', 'project'])->findOrFail($id);
        $pdf = $this->pdfExporter->pdf($report);
        $name = $this->service->exportFilename($report).'.pdf';

        return $request->boolean('inline') ? $pdf->stream($name) : $pdf->download($name);
    }

    public function exportDocx(int $id)
    {
        $report = EnvironmentReport::with(['assets', 'project'])->findOrFail($id);
        $tmp = $this->docxExporter->save($report);
        $name = $this->service->exportFilename($report).'.docx';

        return response()->download($tmp, $name)->deleteFileAfterSend(true);
    }

    // ── Assets ──

    public function assets(int $id): JsonResponse
    {
        $report = EnvironmentReport::findOrFail($id);

        return $this->success($report->assets);
    }

    public function storeAsset(Request $request, int $id): JsonResponse
    {
        $report = EnvironmentReport::findOrFail($id);

        $data = $request->validate([
            'kind' => ['required', 'in:'.implode(',', EnvironmentReport::KINDS)],
            'caption' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:10240', 'extensions:jpg,jpeg,png,pdf'],
        ]);

        $asset = $this->service->addAsset($report, $data['kind'], $request->file('file'), $data['caption'] ?? null);

        return $this->created($asset, 'Asset uploaded.');
    }

    public function updateAsset(Request $request, int $assetId): JsonResponse
    {
        $asset = EnvironmentReportAsset::whereHas('report', function ($q) {
            $q->withoutTrashed();
        })->findOrFail($assetId);

        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $asset = $this->service->updateAsset($asset, $data['caption'] ?? null, $data['sort_order'] ?? null);

        return $this->success($asset, 'Asset updated.');
    }

    public function destroyAsset(int $assetId): JsonResponse
    {
        $asset = EnvironmentReportAsset::whereHas('report', function ($q) {
            $q->withoutTrashed();
        })->findOrFail($assetId);
        $this->service->removeAsset($asset);

        return $this->success(null, 'Asset removed.');
    }

    public function downloadAsset(int $assetId)
    {
        $asset = EnvironmentReportAsset::whereHas('report', function ($q) {
            $q->withoutTrashed();
        })->findOrFail($assetId);
        $disk = Storage::disk('local');

        abort_unless($disk->exists($asset->file_path), 404);

        $extension = strtolower(pathinfo($asset->file_path, PATHINFO_EXTENSION));
        $inlineTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];

        if (isset($inlineTypes[$extension])) {
            return response($disk->get($asset->file_path), 200, [
                'Content-Type' => $inlineTypes[$extension],
                'X-Content-Type-Options' => 'nosniff',
                'Content-Disposition' => 'inline; filename="'.addslashes($asset->file_name).'"',
                // Uploaded files are user content: never let an embedded script run in our origin.
                'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'",
            ]);
        }

        return $disk->download($asset->file_path, $asset->file_name);
    }
}
