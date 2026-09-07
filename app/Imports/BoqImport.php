<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Parses the official BQ template (see BoqTemplateExport) into BQ items.
 *
 * Built to the template's own columns, so there is no format guessing. Rows with
 * no description are skipped (blank spacer rows are common in a real BQ). Amount
 * is always recomputed as Quantity × Rate — the sheet never overrides it, so an
 * imported total can never contradict its own quantity and rate.
 */
class BoqImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public int $skipped = 0;

    public function collection(Collection $collection): void
    {
        $order = 0;

        foreach ($collection as $row) {
            // WithHeadingRow lowercases + snake_cases headers:
            // "Item No" => item_no, "Rate (RM)" => rate_rm.
            $description = trim((string) ($row['description'] ?? ''));

            if ($description === '') {
                $this->skipped++;

                continue;
            }

            $quantity = $this->number($row['quantity'] ?? 0);
            $rate = $this->number($row['rate_rm'] ?? $row['rate'] ?? 0);

            $this->rows[] = [
                'item_no' => $this->stringOrNull($row['item_no'] ?? null),
                'description' => $description,
                'unit' => $this->stringOrNull($row['unit'] ?? null),
                'quantity' => $quantity,
                'rate' => $rate,
                'amount' => round($quantity * $rate, 2),
                'sort_order' => $order++,
            ];
        }
    }

    private function number(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        // Tolerate "1,200.50" and stray spaces from hand-edited sheets.
        return (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
