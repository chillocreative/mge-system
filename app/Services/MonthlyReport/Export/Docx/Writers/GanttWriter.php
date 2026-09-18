<?php

namespace App\Services\MonthlyReport\Export\Docx\Writers;

use App\Services\MonthlyReport\AttachedPages;
use App\Services\MonthlyReport\Export\Docx\DocxDocument;
use App\Services\MonthlyReport\Export\Docx\Writers\Concerns\WritesAttachedPages;
use PhpOffice\PhpWord\Shared\Converter;

/**
 * Renders 2.5 ACTUAL WORK PROGRESS: the "attached on the following N page(s)" line when Gantt
 * pages were uploaded, the rows table if any (s2-5.blade.php), then the uploaded gantt_page
 * assets themselves — image pages become full-width images in their own landscape section, PDF
 * pages become a one-line "Attached: …" reference (the PDF export embeds those pages instead).
 * Leaves the current section in whatever orientation the last Gantt asset needed (landscape, if
 * any image page was inserted); the exporter is responsible for restoring the chunk's original
 * orientation via DocxDocument::ensureSection() before writing the next thing, so a "restore"
 * that ends up writing nothing never materialises an empty section/page.
 */
final class GanttWriter implements SectionWriter
{
    use WritesAttachedPages;

    public function write(DocxDocument $doc, string $key, array $data, ?string $note, array $ctx): void
    {
        $rows = $data['rows'] ?? [];
        $version = $data['version'] ?? null;
        $assets = $this->attachedAssets($ctx, AttachedPages::kindFor('2.5'));
        $imagePages = $this->attachedImagePageCount($assets);
        $hasPdfOnly = $imagePages === 0 && $assets->isNotEmpty();

        if ($version) {
            $caption = "Programme: {$version['label']}";
            if (! empty($version['status_date'])) {
                $caption .= " (status date {$version['status_date']})";
            }
            $doc->paragraph($caption);
        }

        $this->writeAttachedMessage($doc, $assets, 'The work programme (Gantt chart)');

        if (empty($rows)) {
            if ($imagePages === 0 && ! $hasPdfOnly) {
                $doc->paragraph('No data.', ['italic' => true, 'color' => '555555']);
            }
        } else {
            $isLandscape = ($ctx['orientation'] ?? 'landscape') === 'landscape';
            $headers = ['No.', 'Task', 'Duration', 'Start', 'Finish', 'Actual %', 'Plan %'];
            $tableRows = array_map(fn ($row) => [
                $row['no'] ?? '',
                $row['task'] ?? '',
                $row['duration'] ?? '',
                $row['start'] ?? '',
                $row['finish'] ?? '',
                $row['actual'] ?? '',
                $row['plan'] ?? '',
            ], array_values($rows));

            $levels = array_map(fn ($row) => max(1, (int) ($row['level'] ?? 1)), array_values($rows));
            $summaries = array_map(fn ($row) => (bool) ($row['summary'] ?? false), array_values($rows));

            $doc->table($headers, $tableRows, [
                'fontSize' => $isLandscape ? 6 : 7,
                'repeatHeader' => true,
                'cellStyle' => function (int $r, int $c) use ($levels, $summaries) {
                    $style = ['bold' => $summaries[$r] ?? false];
                    if ($c === 1) {
                        $style['indent'] = (int) round(Converter::cmToTwip((($levels[$r] ?? 1) - 1) * 0.3));
                    }

                    return $style;
                },
            ]);
        }

        $this->writeAttachedAssets($doc, $assets);
    }
}
