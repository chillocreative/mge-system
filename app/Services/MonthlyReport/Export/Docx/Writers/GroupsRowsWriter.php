<?php

namespace App\Services\MonthlyReport\Export\Docx\Writers;

use App\Services\MonthlyReport\Export\Docx\DocxDocument;

/**
 * Renders 3.2 LIST OF PENDING CORRESPONDENCE: a heading + row table per non-empty
 * correspondence group — mirrors s3-2.blade.php.
 */
final class GroupsRowsWriter implements SectionWriter
{
    public function write(DocxDocument $doc, string $key, array $data, ?string $note, array $ctx): void
    {
        $groups = array_values(array_filter($data['groups'] ?? [], fn ($g) => ! empty($g['rows'])));

        if (empty($groups)) {
            $doc->paragraph('No data.', ['italic' => true, 'color' => '555555']);

            return;
        }

        foreach ($groups as $group) {
            $doc->heading(trim(($group['label'] ?? $group['code'] ?? '').' ('.($group['ref_prefix'] ?? '').')'), 2);

            $rows = array_map(fn ($row) => [
                $row['no'] ?? '',
                $row['reference'] ?? '',
                $row['title'] ?? '',
                $row['issued'] ?? '-',
                $row['approved'] ?? '-',
                $row['reminder'] ?? '-',
                $row['status'] ?? '',
            ], $group['rows']);

            $doc->table(['No.', 'Reference', 'Title', 'Issued', 'Approved', 'Reminder', 'Status'], $rows, ['fontSize' => 8]);
        }
    }
}
