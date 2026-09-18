<?php

namespace App\Services\MonthlyReport\Export\Docx\Writers;

use App\Services\MonthlyReport\Export\Docx\DocxDocument;

/**
 * Renders 4.1 MONTHLY TRADE WORKER / 4.2 MONTHLY MACHINERIES & PLANT: a No./Description column
 * pair plus one column per day, a month header row (colspan-grouped) above the day-number row,
 * optional group rows (4.1 only, full-width bold), and a computed totals row — mirrors
 * s4-1.blade.php / s4-2.blade.php. Portrait chunks 16 days at a time; landscape renders every
 * day in one table, at 6pt to fit.
 */
final class MatrixWriter implements SectionWriter
{
    private const HEADER_FILL = 'D9EAD3';

    public function write(DocxDocument $doc, string $key, array $data, ?string $note, array $ctx): void
    {
        $days = $data['days'] ?? [];
        $hasGroups = array_key_exists('groups', $data);
        $groups = $hasGroups ? ($data['groups'] ?? []) : [['label' => null, 'rows' => $data['rows'] ?? []]];

        if (empty($days) || ($hasGroups ? empty($data['groups']) : empty($data['rows']))) {
            $doc->paragraph('No data.', ['italic' => true, 'color' => '555555']);

            return;
        }

        $totals = $data['totals'] ?? [];
        $isLandscape = ($ctx['orientation'] ?? 'portrait') === 'landscape';
        $dayIndexes = array_keys($days);
        $chunks = $isLandscape ? [$dayIndexes] : array_chunk($dayIndexes, 16);

        foreach ($chunks as $chunk) {
            $this->writeChunk($doc, $days, $groups, $hasGroups, $totals, $chunk);
        }
    }

    private function writeChunk(DocxDocument $doc, array $days, array $groups, bool $hasGroups, array $totals, array $chunk): void
    {
        $rows = [];
        $boldRows = [];

        $monthRow = [['text' => 'No.', 'colspan' => 1], ['text' => 'Description', 'colspan' => 1]];
        foreach ($this->monthRuns($chunk, $days) as $run) {
            $monthRow[] = ['text' => $run['month'], 'colspan' => $run['span']];
        }
        $rows[] = $monthRow;
        $boldRows[] = 0;

        $dayRow = ['', ''];
        foreach ($chunk as $i) {
            $dayRow[] = $days[$i]['label'] ?? $days[$i]['date'] ?? '';
        }
        $rows[] = $dayRow;
        $boldRows[] = 1;

        $no = 1;
        foreach ($groups as $group) {
            if ($hasGroups) {
                $rows[] = [['text' => $group['label'] ?? '', 'colspan' => count($chunk) + 2]];
                $boldRows[] = count($rows) - 1;
            }

            foreach (($group['rows'] ?? []) as $row) {
                $dataRow = [$no++, $row['description'] ?? ''];
                foreach ($chunk as $i) {
                    $dataRow[] = $row['counts'][$i] ?? '-';
                }
                $rows[] = $dataRow;
            }
        }

        $totalRow = [['text' => 'Total', 'colspan' => 2]];
        foreach ($chunk as $i) {
            $t = $totals[$i] ?? null;
            $totalRow[] = empty($t) ? '-' : $t;
        }
        $rows[] = $totalRow;
        $boldRows[] = count($rows) - 1;

        $doc->table(null, $rows, [
            'fontSize' => 6,
            'bold' => $boldRows,
            'shading' => fn ($r, $c) => $r < 2 ? self::HEADER_FILL : null,
        ]);
    }

    /** @return array<int, array{month: string, span: int}> */
    private function monthRuns(array $chunk, array $days): array
    {
        $runs = [];
        $currentMonth = null;
        $span = 0;

        foreach ($chunk as $i) {
            $month = $days[$i]['month'] ?? '';
            if ($month === $currentMonth) {
                $span++;

                continue;
            }
            if ($currentMonth !== null) {
                $runs[] = ['month' => $currentMonth, 'span' => $span];
            }
            $currentMonth = $month;
            $span = 1;
        }
        if ($currentMonth !== null) {
            $runs[] = ['month' => $currentMonth, 'span' => $span];
        }

        return $runs;
    }
}
