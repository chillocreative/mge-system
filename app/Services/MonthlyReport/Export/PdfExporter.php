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

        if ($keys !== null) {
            $data['sections'] = array_intersect_key($data['sections'], array_flip($keys));
            $data['notes'] = array_intersect_key($data['notes'], array_flip($keys));
            $data['showCover'] = in_array('cover', $keys, true);
            $data['orderedKeys'] = array_values(array_filter($keys, fn ($key) => $key !== 'cover'));
        } else {
            $data['showCover'] = true;
            $data['orderedKeys'] = array_values(array_filter($data['tocKeys'], fn ($key) => $key !== 'cover'));
        }

        $data['orientation'] = $orientation;

        return view('pdf.monthly-report.layout', $data)->render();
    }

    public function render(MonthlyReport $report): string
    {
        $keys = $report->sections->where('include', true)->sortBy('sort_order')->pluck('key')->values()->all();

        if ($keys === []) {
            throw ValidationException::withMessages(['sections' => 'Include at least one section before exporting.']);
        }

        $chunks = OrientationPlanner::plan($keys, $report->options['landscape_sections'] ?? null);

        $parts = [];
        foreach ($chunks as $chunk) {
            $html = $this->html($report, $chunk['keys'], $chunk['orientation']);
            $parts[] = ['pdf' => Pdf::loadHTML($html)->setPaper('a4', $chunk['orientation'])->output()];
            if (in_array('2.5', $chunk['keys'], true)) {
                $parts = array_merge($parts, $this->ganttParts($report)); // Task 5 fills this in
            }
        }

        return $this->merger->merge($parts, "Monthly Progress Report No.{$report->report_no}");
    }

    /** @return array<int, array{pdf?: string, file?: string}> */
    private function ganttParts(MonthlyReport $report): array
    {
        // Task 5 will insert the uploaded/rendered Gantt chart pages here.
        return [];
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

        foreach (['2.2' => '2.2 PHYSICAL S-CURVE', '2.4' => '2.4 FINANCIAL S-CURVE'] as $key => $chartTitle) {
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
                $sections[$key]['chart_svg_uri'] = SCurveSvg::dataUri((new SCurveSvg)->render($series, [
                    'title' => $chartTitle,
                    'unit' => $key === '2.4' ? 'RM' : '%',
                ]));
            } catch (\Throwable $e) {
                Log::warning("Monthly report chart {$key} could not be rendered: {$e->getMessage()}", ['report_id' => $report->id]);
            }
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
