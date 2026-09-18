<?php

namespace App\Services\MonthlyReport\Export\Docx\Writers;

use App\Services\MonthlyReport\Export\Docx\DocxDocument;

/**
 * Renders a section whose data is a list of {label, value} rows (currently 1.1 PROJECT
 * INFORMATION) as a two-column key/value table, mirroring s1-1.blade.php.
 */
final class KeyValueWriter implements SectionWriter
{
    public function write(DocxDocument $doc, string $key, array $data, ?string $note, array $ctx): void
    {
        $rows = $data['rows'] ?? [];

        if (empty($rows)) {
            $doc->paragraph('No data.', ['italic' => true, 'color' => '555555']);

            return;
        }

        $pairs = [];
        foreach ($rows as $row) {
            $pairs[(string) ($row['label'] ?? '')] = $row['value'] ?? '-';
        }

        $doc->keyValue($pairs);
    }
}
