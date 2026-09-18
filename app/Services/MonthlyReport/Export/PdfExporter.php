<?php

namespace App\Services\MonthlyReport\Export;

use App\Models\MonthlyReport;
use App\Models\ReportImage;
use App\Services\MonthlyReport\Charts\SCurveSvg;
use App\Services\MonthlyReport\ReportContext;
use App\Services\MonthlyReport\SectionRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class PdfExporter
{
    private const IMAGE_MIME = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'];

    private const MAX_IMAGE_BYTES = 3 * 1024 * 1024;

    /** @var array<int, ReportImage> */
    private array $imageCache = [];

    public function __construct(private PdfMerger $merger) {}

    /**
     * Renders the layout view for a single chunk, returned as a plain HTML string.
     *
     * @param  string[]|null  $keys  When given, only these section keys are rendered (in order); the
     *                               cover appears only when 'cover' is among them. Null renders every
     *                               included section (used by tests and the legacy single-document path).
     */
    public function html(MonthlyReport $report, ?array $keys = null, string $orientation = 'portrait'): string
    {
        $data = $this->viewData($report);

        // Canonical registry order, restricted to sections actually included in this report —
        // used for the table of contents (always lists the whole report) regardless of chunk.
        $data['tocKeys'] = array_values(array_intersect(array_keys(SectionRegistry::TITLES), array_keys($data['sections'])));

        // Whole-document mode ($keys === null) always shows the cover (when present) and the
        // TOC, so force 'cover' into the key list purely to flip showCover — chunkHtml() strips
        // it back out of orderedKeys regardless of whether it was already there.
        $effectiveKeys = $keys ?? array_merge(['cover'], $data['tocKeys']);

        return $this->chunkHtml($data, $effectiveKeys, $orientation);
    }

    public function render(MonthlyReport $report): string
    {
        $keys = $report->sections->where('include', true)->sortBy('sort_order')->pluck('key')->values()->all();

        if ($keys === []) {
            throw ValidationException::withMessages(['sections' => 'Include at least one section before exporting.']);
        }

        $chunks = OrientationPlanner::plan($keys, $report->options['landscape_sections'] ?? null);

        // Built once up front (not per chunk) since it re-reads and base64-encodes every
        // photo in the report — expensive to repeat 5-8x for a multi-chunk export.
        $data = $this->viewData($report);
        $data['tocKeys'] = array_values(array_intersect(array_keys(SectionRegistry::TITLES), array_keys($data['sections'])));

        $parts = [];
        $ganttInserted = false;
        foreach ($chunks as $index => $chunk) {
            $html = $this->chunkHtml($data, $chunk['keys'], $chunk['orientation'], $index === 0);
            $parts[] = ['pdf' => Pdf::loadHTML($html)->setPaper('a4', $chunk['orientation'])->output()];
            if (in_array('2.5', $chunk['keys'], true)) {
                $parts = array_merge($parts, $this->ganttParts($report));
                $ganttInserted = true;
            }
        }

        // Section 2.5 is excluded from most reports by default, but uploaded Gantt pages should
        // still be appended even when there is no 2.5 chunk to anchor them to.
        if (! $ganttInserted) {
            $parts = array_merge($parts, $this->ganttParts($report));
        }

        return $this->merger->merge($parts, "Monthly Progress Report No.{$report->report_no}");
    }

    /** Renders the layout view for a single chunk from pre-built view data (see viewData()). */
    private function chunkHtml(array $data, array $keys, string $orientation, bool $isFirstChunk = true): string
    {
        $data['sections'] = array_intersect_key($data['sections'], array_flip($keys));
        $data['notes'] = array_intersect_key($data['notes'], array_flip($keys));
        $data['showCover'] = in_array('cover', $keys, true);
        $data['orderedKeys'] = array_values(array_filter($keys, fn ($key) => $key !== 'cover'));
        $data['orientation'] = $orientation;
        $data['isFirstChunk'] = $isFirstChunk;

        return view('pdf.monthly-report.layout', $data)->render();
    }

    /** @return array<int, array{pdf?: string, file?: string, label?: string}> */
    private function ganttParts(MonthlyReport $report): array
    {
        $assets = $report->assets()->where('kind', 'gantt_page')->orderBy('sort_order')->orderBy('id')->get();

        $parts = [];
        foreach ($assets as $asset) {
            $ext = strtolower($asset->extension ?? pathinfo($asset->file_path, PATHINFO_EXTENSION));
            $label = $asset->file_name ?: basename($asset->file_path);

            if ($ext === 'pdf') {
                if (! Storage::disk('local')->exists($asset->file_path)) {
                    Log::warning("Monthly report Gantt PDF asset missing from disk: {$asset->file_path}", ['report_id' => $report->id, 'asset_id' => $asset->id]);

                    continue;
                }

                $parts[] = ['file' => Storage::disk('local')->path($asset->file_path), 'label' => $label];

                continue;
            }

            $mime = self::IMAGE_MIME[$ext] ?? null;
            if (! $mime || ! Storage::disk('local')->exists($asset->file_path)) {
                continue;
            }

            try {
                $uri = 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($asset->file_path));
                $html = view('pdf.monthly-report.asset-image', ['uri' => $uri])->render();
                $parts[] = ['pdf' => Pdf::loadHTML($html)->setPaper('a4', 'landscape')->output()];
            } catch (\Throwable $e) {
                // DomPDF's PNG-alpha handling calls GD unguarded; on a GD-less host this
                // throws \Error rather than degrading gracefully.
                Log::warning("Monthly report Gantt image {$label} could not be rendered: {$e->getMessage()}", ['report_id' => $report->id, 'asset_id' => $asset->id]);
                $parts[] = ['pdf' => $this->placeholderPdf("Attached page could not be read: {$label}"), 'label' => $label];
            }
        }

        return $parts;
    }

    private function placeholderPdf(string $message): string
    {
        $html = view('pdf.monthly-report.placeholder', ['message' => $message])->render();

        return Pdf::loadHTML($html)->setPaper('a4', 'landscape')->output();
    }

    private function viewData(MonthlyReport $report): array
    {
        $report->loadMissing(['sections', 'project', 'period']);
        $ctx = ReportContext::for($report);

        $included = $report->sections->filter(fn ($section) => $section->include);

        $imageIds = [];
        foreach ($included as $section) {
            foreach ($this->collectImageIds($section->key, $section->merged) as $id) {
                $imageIds[$id] = true;
            }
        }
        $this->imageCache = $imageIds
            ? ReportImage::whereIn('id', array_keys($imageIds))->get()->keyBy('id')->all()
            : [];

        $sections = [];
        $notes = [];
        foreach ($included as $section) {
            $sections[$section->key] = $this->embedImages($section->key, $section->merged);
            $notes[$section->key] = $section->notes;
        }

        foreach (['2.2', '2.4'] as $key) {
            if (! isset($sections[$key])) {
                continue;
            }
            $series = $sections[$key]['series'] ?? [];
            $sections[$key]['chart_svg_uri'] = null;
            if (empty($series['months'])) {
                continue;
            }
            // A malformed override must degrade to "no chart", never abort the whole PDF.
            try {
                $sections[$key]['chart_svg_uri'] = SCurveSvg::dataUri((new SCurveSvg)->render($series, SCurveSvg::optionsFor($key) ?? []));
            } catch (\Throwable $e) {
                Log::warning("Monthly report chart {$key} could not be rendered: {$e->getMessage()}", ['report_id' => $report->id]);
            }
        }

        if (isset($sections['2.5'])) {
            $sections['2.5']['gantt_pages'] = (int) $report->assets()->where('kind', 'gantt_page')->sum('pages');
        }

        if (isset($sections['cover'])) {
            $sections['cover']['signatories'] = $report->signatories ?: ($sections['cover']['signatories'] ?? []);
            if ($report->evaluation_date) {
                $sections['cover']['evaluation_date'] = $report->evaluation_date->copy()->format('d M Y');
            }
        }

        $logos = [];
        foreach (['owner', 'superintending_officer', 'consultant', 'contractor'] as $role) {
            $party = $ctx->party($role);
            $logos[$role] = $party ? $this->dataUriFromDisk($party->logo_path) : null;
        }

        return [
            'report' => $report,
            'ctx' => $ctx,
            'sections' => $sections,
            'notes' => $notes,
            'logos' => $logos,
            'mgeLogo' => $this->mgeLogo(),
            'titles' => SectionRegistry::TITLES,
        ];
    }

    /** @return array<int, int> image ids referenced by this section */
    private function collectImageIds(string $key, array $data): array
    {
        $ids = [];

        if ($key === '1.3') {
            foreach ($data['images'] ?? [] as $img) {
                if (isset($img['id'])) {
                    $ids[] = (int) $img['id'];
                }
            }
        }

        if ($key === '5.0') {
            foreach (['site_access', 'key_plan'] as $group) {
                foreach ($data[$group] ?? [] as $img) {
                    if (isset($img['id'])) {
                        $ids[] = (int) $img['id'];
                    }
                }
            }
            foreach ($data['pairs'] ?? [] as $pair) {
                foreach (['previous', 'current'] as $slot) {
                    if (isset($pair[$slot]['id'])) {
                        $ids[] = (int) $pair[$slot]['id'];
                    }
                }
            }
        }

        return $ids;
    }

    private function embedImages(string $key, array $data): array
    {
        if ($key === '1.3' && isset($data['images'])) {
            $data['images'] = array_map(fn ($img) => $this->withDataUri($img), $data['images']);
        }

        if ($key === '5.0') {
            $data['site_access'] = array_map(fn ($img) => $this->withDataUri($img), $data['site_access'] ?? []);
            $data['key_plan'] = array_map(fn ($img) => $this->withDataUri($img), $data['key_plan'] ?? []);
            $data['pairs'] = array_map(function ($pair) {
                $pair['previous'] = $pair['previous'] ? $this->withDataUri($pair['previous']) : null;
                $pair['current'] = $pair['current'] ? $this->withDataUri($pair['current']) : null;

                return $pair;
            }, $data['pairs'] ?? []);
        }

        return $data;
    }

    private function withDataUri(array $image): array
    {
        $image['data_uri'] = null;

        if (isset($image['id']) && ($reportImage = $this->imageCache[(int) $image['id']] ?? null)) {
            if ($this->fileTooLarge($reportImage->file_path)) {
                $image['caption'] = trim(($image['caption'] ?? '').' (image too large)');
            } else {
                $image['data_uri'] = $this->dataUriFromDisk($reportImage->file_path);
            }
        }

        return $image;
    }

    private function fileTooLarge(?string $path): bool
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return false;
        }

        return Storage::disk('local')->size($path) > self::MAX_IMAGE_BYTES;
    }

    private function dataUriFromDisk(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = self::IMAGE_MIME[$ext] ?? null;
        if (! $mime) {
            // webp (and anything else) is not rendered by DomPDF — skip it.
            return null;
        }

        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($path));
    }

    private function mgeLogo(): ?string
    {
        $path = public_path('logo.png');
        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(file_get_contents($path));
    }
}
