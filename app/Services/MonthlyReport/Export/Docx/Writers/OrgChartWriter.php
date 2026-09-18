<?php

namespace App\Services\MonthlyReport\Export\Docx\Writers;

use App\Services\MonthlyReport\Export\Docx\DocxDocument;
use PhpOffice\PhpWord\Shared\Converter;

/**
 * Renders the 1.4 ORGANISATION CHART as an indented tree list (bullet, bold name, optional
 * " — designation"), one node per line, each level indented 0.6 cm further than its parent —
 * mirrors s1-4.blade.php / s1-4-node.blade.php's flat indented-div rendering. Placeholder names
 * such as "TBA" are not treated specially; they render as plain text like any other name.
 */
final class OrgChartWriter implements SectionWriter
{
    private const INDENT_CM_PER_LEVEL = 0.6;

    public function write(DocxDocument $doc, string $key, array $data, ?string $note, array $ctx): void
    {
        $tree = $data['tree'] ?? [];

        if (empty($tree)) {
            $doc->paragraph('No data.', ['italic' => true, 'color' => '555555']);

            return;
        }

        $this->renderNodes($doc, $tree, 0);
    }

    private function renderNodes(DocxDocument $doc, array $nodes, int $depth): void
    {
        $indentTwips = (int) round(Converter::cmToTwip($depth * self::INDENT_CM_PER_LEVEL));

        foreach ($nodes as $node) {
            $runs = [
                ['text' => '• ', 'style' => []],
                ['text' => (string) ($node['name'] ?? ''), 'style' => ['bold' => true]],
            ];
            if (! empty($node['designation'])) {
                $runs[] = ['text' => ' — '.$node['designation'], 'style' => []];
            }

            $doc->paragraphRuns($runs, ['indentation' => ['left' => $indentTwips]]);

            if (! empty($node['children'])) {
                $this->renderNodes($doc, $node['children'], $depth + 1);
            }
        }
    }
}
