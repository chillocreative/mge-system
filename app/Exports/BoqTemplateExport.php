<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * The official BQ import template (plan §5.8).
 *
 * Handing users a template to fill removes the guesswork the plan warns about
 * (F1–F9): the importer parses exactly these columns, so what HR downloads is
 * by definition what the importer accepts. "Amount" is intentionally omitted —
 * it is Qty × Rate and computed on import, so it can never disagree with itself.
 */
class BoqTemplateExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function headings(): array
    {
        return ['Item No', 'Description', 'Unit', 'Quantity', 'Rate (RM)'];
    }

    public function array(): array
    {
        // A couple of example rows so the expected shape is obvious. Users clear
        // these and enter their own.
        return [
            ['A', 'PRELIMINARIES', '', '', ''],
            ['A.1', 'Site clearing and setting out', 'm2', 1200, 3.50],
            ['A.2', 'Provision of temporary site office', 'unit', 1, 5000],
            ['B', 'EARTHWORKS', '', '', ''],
            ['B.1', 'Excavation for foundation, not exceeding 2m deep', 'm3', 350, 28.00],
        ];
    }

    public function title(): string
    {
        return 'BQ Template';
    }
}
