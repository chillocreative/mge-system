<?php

namespace App\Services\MonthlyReport\Export;

use App\Models\MonthlyReport;
use App\Models\ReportImage;
use App\Services\MonthlyReport\ReportContext;
use App\Services\MonthlyReport\SectionRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

final class PdfExporter
{
    private const IMAGE_MIME = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'];

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

        $sections = [];
        $notes = [];
        foreach ($report->sections as $section) {
            if (! $section->include) {
                continue;
            }
            $sections[$section->key] = $this->embedImages($section->key, $section->merged);
            $notes[$section->key] = $section->notes;
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
        $image['data_uri'] = isset($image['id']) ? $this->reportImageDataUri((int) $image['id']) : null;

        return $image;
    }

    private function reportImageDataUri(int $imageId): ?string
    {
        $image = ReportImage::find($imageId);

        return $image ? $this->dataUriFromDisk($image->file_path) : null;
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
