<?php

namespace App\Services\MonthlyReport\Export\Docx\Writers;

use App\Services\MonthlyReport\Export\Docx\DocxDocument;

/**
 * Renders the 2.1 SUMMARY WORK PROGRESS section: the planning-days line, then two fixed
 * Item | Previous | Current tables (Physical Progress, Financial Progress) — mirrors
 * s2-1.blade.php.
 */
final class ProgressWriter implements SectionWriter
{
    public function write(DocxDocument $doc, string $key, array $data, ?string $note, array $ctx): void
    {
        $physicalRows = $data['physical']['rows'] ?? [];
        $financialRows = $data['financial']['rows'] ?? [];

        if (empty($physicalRows) && empty($financialRows)) {
            $doc->paragraph('No data.', ['italic' => true, 'color' => '555555']);

            return;
        }

        $doc->paragraph('Planning Days to Completion: '.($data['planning_days'] ?? '-'));

        $doc->heading('Physical Progress', 2);
        $this->table($doc, $data['physical'] ?? []);

        $doc->heading('Financial Progress', 2);
        $this->table($doc, $data['financial'] ?? []);
    }

    private function table(DocxDocument $doc, array $section): void
    {
        $rows = $section['rows'] ?? [];
        $headers = ['Item', $section['prev_label'] ?? 'Previous', $section['cur_label'] ?? 'Current'];

        $tableRows = array_map(fn ($row) => [
            $row['label'] ?? '',
            $row['prev'] ?? '-',
            $row['cur'] ?? '-',
        ], $rows);

        $doc->table($headers, $tableRows, ['fontSize' => 8]);
    }
}
