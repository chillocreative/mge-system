<?php

namespace App\Services\MonthlyReport\Export\Docx\Writers;

use App\Services\MonthlyReport\Export\Docx\DocxDocument;

/**
 * Fallback writer for any section key without a dedicated writer: renders the generic
 * `rows` table (columns inferred from the first row's keys) when present, otherwise "No data.".
 */
final class TextWriter implements SectionWriter
{
    public function write(DocxDocument $doc, string $key, array $data, ?string $note, array $ctx): void
    {
        $rows = $data['rows'] ?? null;

        if (empty($data) || ! empty($data['placeholder']) || empty($rows)) {
            $doc->paragraph('No data.', ['italic' => true, 'color' => '555555']);

            return;
        }

        $columns = array_keys($rows[array_key_first($rows)]);
        $headers = array_map(fn ($column) => ucfirst(str_replace('_', ' ', (string) $column)), $columns);

        $tableRows = [];
        foreach ($rows as $row) {
            $tableRows[] = array_map(function ($column) use ($row) {
                $value = $row[$column] ?? null;

                return is_scalar($value) || $value === null ? $value : json_encode($value);
            }, $columns);
        }

        $doc->table($headers, $tableRows, ['fontSize' => 8]);
    }
}
