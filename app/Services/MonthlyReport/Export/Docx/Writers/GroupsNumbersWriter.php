<?php

namespace App\Services\MonthlyReport\Export\Docx\Writers;

use App\Services\MonthlyReport\Export\Docx\DocxDocument;

/**
 * Renders 3.1 SUMMARY OF DOCUMENTS SUBMISSION: one row per correspondence group with
 * Accumulative / Previous / Current issued-open-closed counts — mirrors s3-1.blade.php.
 * DocxDocument's table() has no rowspan support, so the two-row header (a true rowspan in the
 * Blade) is rendered as two shaded header rows instead of one merged cell.
 */
final class GroupsNumbersWriter implements SectionWriter
{
    private const HEADER_FILL = 'D9EAD3';

    public function write(DocxDocument $doc, string $key, array $data, ?string $note, array $ctx): void
    {
        $groups = $data['groups'] ?? [];

        if (empty($groups)) {
            $doc->paragraph('No data.', ['italic' => true, 'color' => '555555']);

            return;
        }

        $rows = [
            [
                ['text' => 'Type', 'colspan' => 1],
                ['text' => 'Accumulative', 'colspan' => 3],
                ['text' => 'Previous ('.($data['previous_label'] ?? '-').')', 'colspan' => 3],
                ['text' => 'Current ('.($data['current_label'] ?? '-').')', 'colspan' => 3],
            ],
            ['', 'Issued', 'Open', 'Closed', 'Issued', 'Open', 'Closed', 'Issued', 'Open', 'Closed'],
        ];

        foreach ($groups as $group) {
            $rows[] = [
                $group['label'] ?? $group['code'] ?? '',
                $group['accumulative']['issued'] ?? 0,
                $group['accumulative']['open'] ?? 0,
                $group['accumulative']['closed'] ?? 0,
                $group['previous']['issued'] ?? 0,
                $group['previous']['open'] ?? 0,
                $group['previous']['closed'] ?? 0,
                $group['current']['issued'] ?? 0,
                $group['current']['open'] ?? 0,
                $group['current']['closed'] ?? 0,
            ];
        }

        $doc->table(null, $rows, [
            'fontSize' => 8,
            'bold' => [0, 1],
            'shading' => fn ($r, $c) => $r < 2 ? self::HEADER_FILL : null,
        ]);
    }
}
