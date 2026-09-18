<?php

namespace App\Services\MonthlyReport\Export\Docx\Writers;

use App\Services\MonthlyReport\Export\Docx\DocxDocument;
use Illuminate\Support\Facades\Storage;

/**
 * Renders 2.5 ACTUAL WORK PROGRESS: the "attached on the following N page(s)" line when Gantt
 * pages were uploaded, the rows table if any (s2-5.blade.php), then the uploaded gantt_page
 * assets themselves — image pages become full-width images in their own landscape section, PDF
 * pages become a one-line "Attached: …" reference (the PDF export embeds those pages instead).
 * Ends by reopening a section in the chunk's original orientation so later writes (the note,
 * and the next chunk's own newSection() call) aren't left mid-landscape.
 */
final class GanttWriter implements SectionWriter
{
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg'];

    public function write(DocxDocument $doc, string $key, array $data, ?string $note, array $ctx): void
    {
        $ganttPages = (int) ($data['gantt_pages'] ?? 0);
        $rows = $data['rows'] ?? [];

        if ($ganttPages > 0) {
            $doc->paragraph("The work programme (Gantt chart) is attached on the following {$ganttPages} page(s).");
        }

        if (empty($rows)) {
            if ($ganttPages === 0) {
                $doc->paragraph('No data.', ['italic' => true, 'color' => '555555']);
            }
        } else {
            $headers = ['No.', 'Task', 'Duration', 'Start', 'Finish', 'Actual', 'Plan'];
            $tableRows = array_map(fn ($row) => [
                $row['no'] ?? '',
                $row['task'] ?? '',
                $row['duration'] ?? '',
                $row['start'] ?? '',
                $row['finish'] ?? '',
                $row['actual'] ?? '',
                $row['plan'] ?? '',
            ], $rows);

            $doc->table($headers, $tableRows, ['fontSize' => 8]);
        }

        $this->writeGanttAssets($doc, $ctx);
    }

    private function writeGanttAssets(DocxDocument $doc, array $ctx): void
    {
        $report = $ctx['report'] ?? null;
        if ($report === null) {
            return;
        }

        $assets = $report->assets()->where('kind', 'gantt_page')->orderBy('sort_order')->orderBy('id')->get();
        if ($assets->isEmpty()) {
            return;
        }

        $insertedLandscape = false;

        foreach ($assets as $asset) {
            $ext = strtolower($asset->extension ?? pathinfo($asset->file_path, PATHINFO_EXTENSION));
            $label = $asset->file_name ?: basename($asset->file_path);

            if (in_array($ext, self::IMAGE_EXTENSIONS, true)) {
                if (! Storage::disk('local')->exists($asset->file_path)) {
                    continue;
                }

                $doc->newSection('landscape');
                $insertedLandscape = true;
                $doc->image(Storage::disk('local')->get($asset->file_path));

                continue;
            }

            $pages = $asset->pages ?? 1;
            $doc->paragraph("• Attached: {$label} ({$pages} pages) — see the PDF export for the embedded pages.");
        }

        if ($insertedLandscape) {
            $doc->newSection($ctx['orientation'] ?? 'portrait');
        }
    }
}
