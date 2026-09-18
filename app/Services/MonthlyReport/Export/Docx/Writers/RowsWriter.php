<?php

namespace App\Services\MonthlyReport\Export\Docx\Writers;

use App\Services\MonthlyReport\Export\Docx\DocxDocument;
use App\Services\MonthlyReport\Export\Docx\SectionSpecs;

/**
 * Generic row-table writer, driven by SectionSpecs::for($key) for column order/labels/
 * formatting. Used for the sections whose Blade partial is "a table of rows" with no other
 * layout: 1.2, 1.5, 2.3, 2.5, 2.6, 3.4, 3.6, 3.7.
 */
final class RowsWriter implements SectionWriter
{
    public function write(DocxDocument $doc, string $key, array $data, ?string $note, array $ctx): void
    {
        $spec = SectionSpecs::for($key);
        $rowsKey = $spec['rowsKey'] ?? 'rows';
        $rows = $data[$rowsKey] ?? [];

        if ($spec !== null && ! empty($spec['preamble']) && array_key_exists($spec['preamble'], $data)) {
            $doc->paragraph('Company: '.($data[$spec['preamble']] ?: '-'));
        }

        if (empty($rows)) {
            $doc->paragraph('No data.', ['italic' => true, 'color' => '555555']);

            return;
        }

        $columns = $spec['columns'] ?? $this->inferColumns($rows[array_key_first($rows)]);
        $headers = array_map(fn ($column) => $column['label'], $columns);
        $align = array_map(fn ($column) => $column['align'] ?? null, $columns);

        $tableRows = [];
        foreach ($rows as $row) {
            $tableRows[] = array_map(fn ($column) => $this->cellValue($row, $column), $columns);
        }

        $doc->table($headers, $tableRows, ['fontSize' => 8, 'align' => $align]);
    }

    /**
     * @param  array<int, array{key: string, label: string, type?: string, default?: mixed}>  $columns
     * @return string|array<int, string> a plain value, or one array item per line for
     *                                   DocxDocument::table()'s multi-line cell support
     */
    private function cellValue(array $row, array $column): string|array
    {
        $type = $column['type'] ?? 'text';
        $key = $column['key'];

        if ($type === 'contacts') {
            $contacts = $row[$key] ?? [];
            if (empty($contacts)) {
                return '-';
            }

            $lines = [];
            foreach ($contacts as $contact) {
                $lines[] = implode(' — ', [
                    $contact['name'] ?? '-',
                    $contact['designation'] ?? '-',
                    $contact['tel'] ?? '-',
                    $contact['email'] ?? '-',
                ]);
            }

            return $lines;
        }

        $value = $row[$key] ?? null;

        if ($type === 'money') {
            return number_format((float) ($value ?? 0), 2);
        }

        if ($value === null || $value === '') {
            return (string) ($column['default'] ?? '');
        }

        return (string) $value;
    }

    /** @return array<int, array{key: string, label: string}> */
    private function inferColumns(array $row): array
    {
        return array_map(
            fn ($key) => ['key' => $key, 'label' => ucfirst(str_replace('_', ' ', (string) $key))],
            array_keys($row)
        );
    }
}
