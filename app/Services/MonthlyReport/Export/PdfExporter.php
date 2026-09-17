<?php

namespace App\Services\MonthlyReport\Export;

use App\Models\MonthlyReport;
use App\Models\ReportImage;
use App\Services\MonthlyReport\Charts\SCurveSvg;
use App\Services\MonthlyReport\ReportContext;
use App\Services\MonthlyReport\SectionRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

final class PdfExporter
{
    private const IMAGE_MIME = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'];

    private const MAX_IMAGE_BYTES = 3 * 1024 * 1024;

    /** @var array<int, ReportImage> */
    private array $imageCache = [];

    /** Renders the same Blade view used by render(), returned as a plain HTML string (for tests/inspection). */
    public function html(MonthlyReport $report): string
    {
        return view('pdf.monthly-report.layout', $this->viewData($report))->render();
    }

    public function render(MonthlyReport $report): \Barryvdh\DomPDF\PDF
    {
        $pdf = Pdf::loadHTML($this->html($report))->setPaper('a4', 'portrait');
        $pdf->render();

        $canvas = $pdf->getCanvas();
        $canvas->page_text($canvas->get_width() - 120, $canvas->get_height() - 42, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 8, [0, 0, 0]);

        return $pdf;
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
            $sections[$key]['chart_svg_uri'] = empty($series['months'])
                ? null
                : SCurveSvg::dataUri((new SCurveSvg)->render($series, [
                    'title' => $chartTitle,
                    'unit' => $key === '2.4' ? 'RM' : '%',
                ]));
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
