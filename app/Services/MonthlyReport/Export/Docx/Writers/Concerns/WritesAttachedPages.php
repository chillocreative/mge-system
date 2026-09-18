<?php

namespace App\Services\MonthlyReport\Export\Docx\Writers\Concerns;

use App\Services\MonthlyReport\Export\Docx\DocxDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Shared by GanttWriter (2.5) and ChartWriter (2.2/2.4): fetches a report's uploaded "attached
 * page" assets of a given kind and writes them into the DOCX — image pages become full-width
 * images in their own landscape section, PDF pages become a one-line "Attached: …" reference
 * (the PDF export embeds those pages instead) — plus the "attached on the following N page(s)"
 * / "PDF attached separately" summary line.
 */
trait WritesAttachedPages
{
    private const ATTACHED_IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg'];

    private function attachedAssets(array $ctx, string $kind): Collection
    {
        $report = $ctx['report'] ?? null;
        if ($report === null) {
            return collect();
        }

        return $report->assets()->where('kind', $kind)->orderBy('sort_order')->orderBy('id')->get();
    }

    private function isAttachedImage($asset): bool
    {
        $ext = strtolower($asset->extension ?? pathinfo($asset->file_path, PATHINFO_EXTENSION));

        return in_array($ext, self::ATTACHED_IMAGE_EXTENSIONS, true);
    }

    private function attachedImagePageCount(Collection $assets): int
    {
        return $assets->filter(fn ($asset) => $this->isAttachedImage($asset))->count();
    }

    /** Writes the "attached on the following N page(s)" / "PDF attached separately" summary line, if any. */
    private function writeAttachedMessage(DocxDocument $doc, Collection $assets, string $label): void
    {
        $imagePages = $this->attachedImagePageCount($assets);
        $hasPdfOnly = $imagePages === 0 && $assets->isNotEmpty();

        if ($imagePages > 0) {
            $doc->paragraph("{$label} is attached on the following {$imagePages} page(s).");
        } elseif ($hasPdfOnly) {
            $doc->paragraph("{$label} PDF is attached separately (see the PDF export).");
        }
    }

    /** Writes the attached assets themselves: image pages as landscape sections, PDFs as a reference line. */
    private function writeAttachedAssets(DocxDocument $doc, Collection $assets): void
    {
        foreach ($assets as $asset) {
            $label = $asset->file_name ?: basename($asset->file_path);

            if ($this->isAttachedImage($asset)) {
                if (! Storage::disk('local')->exists($asset->file_path)) {
                    continue;
                }

                $doc->newSection('landscape');
                $doc->image(Storage::disk('local')->get($asset->file_path));

                continue;
            }

            $pages = $asset->pages ?? 1;
            $doc->paragraph("• Attached: {$label} ({$pages} pages) — see the PDF export for the embedded pages.");
        }
    }
}
