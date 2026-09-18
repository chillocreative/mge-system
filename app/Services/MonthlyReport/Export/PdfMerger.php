<?php

namespace App\Services\MonthlyReport\Export;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParserException;

class PdfMerger
{
    /**
     * Concatenate DomPDF chunk PDFs and/or uploaded PDF files into a single PDF,
     * stamping every page with a footer containing global page numbers.
     *
     * @param  array<int, array{pdf?: string, file?: string, label?: string}>  $parts  Each part is either
     *                                                                                 ['pdf' => $bytes] (DomPDF output) or ['file' => $absolutePath] (an uploaded PDF; all pages imported).
     *                                                                                 An optional 'label' names the source (e.g. the original filename) for the
     *                                                                                 placeholder page rendered when a part cannot be read.
     */
    public function merge(array $parts, string $footerLeft): string
    {
        $pdf = new Fpdi;
        $pdf->SetAutoPageBreak(false);

        // FPDF (the base of FPDI) has no public API to re-select an already-added
        // page, so the total page count is not known until every part has been
        // imported. Use FPDF's built-in {nb} alias, which is substituted with the
        // final page count at Output() time, and stamp the footer for each page
        // immediately after it is added (while it is still the "current" page).
        $alias = '{nb}';
        $pdf->AliasNbPages($alias);

        $temp = [];
        $pageNo = 0;

        try {
            foreach ($parts as $part) {
                $label = $part['label'] ?? null;
                $path = $part['file'] ?? null;

                if (! $path) {
                    $path = tempnam(sys_get_temp_dir(), 'mpr');
                    file_put_contents($path, $part['pdf']);
                    $temp[] = $path;
                }

                try {
                    $pageNo = $this->importAllPages($pdf, $path, $footerLeft, $alias, $pageNo);
                } catch (\Throwable $e) {
                    Log::warning('Monthly report merge could not read an attached page: '.$e->getMessage(), [
                        'source' => $label ?: basename((string) $path),
                    ]);

                    $placeholderPath = tempnam(sys_get_temp_dir(), 'mpr');
                    file_put_contents($placeholderPath, Pdf::loadHTML(view('pdf.monthly-report.placeholder', [
                        'message' => 'Attached page could not be read: '.($label ?: basename((string) $path)),
                    ])->render())->output());
                    $temp[] = $placeholderPath;

                    $pageNo = $this->importAllPages($pdf, $placeholderPath, $footerLeft, $alias, $pageNo);
                }
            }
        } finally {
            foreach ($temp as $t) {
                @unlink($t);
            }
        }

        return $pdf->Output('S');
    }

    private function importAllPages(Fpdi $pdf, string $path, string $footerLeft, string $alias, int $pageNo): int
    {
        $count = $pdf->setSourceFile($path);

        for ($i = 1; $i <= $count; $i++) {
            $tpl = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl);

            $pageNo++;
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(14, $pdf->GetPageHeight() - 12);
            $pdf->Cell(0, 5, $footerLeft, 0, 0, 'L');
            // Left-aligned at a fixed X so the {nb} substitution cannot shift the text.
            $pdf->SetXY(-44, $pdf->GetPageHeight() - 12);
            $pdf->Cell(30, 5, "Page {$pageNo} of {$alias}", 0, 0, 'L');
        }

        return $pageNo;
    }

    /**
     * Verify that FPDI can import the given PDF and return its page count.
     *
     * @throws \RuntimeException When the file cannot be parsed/imported.
     */
    public function probe(string $absolutePath): int
    {
        try {
            $pdf = new Fpdi;
            $count = $pdf->setSourceFile($absolutePath);
            for ($i = 1; $i <= $count; $i++) {
                $pdf->importPage($i);
            }

            return $count;
        } catch (PdfParserException|\Throwable $e) {
            throw new \RuntimeException('This PDF uses a compression the report merger cannot read. Re-save it as PDF 1.4 (Print to PDF) or upload PNG/JPG pages instead.', 0, $e);
        }
    }
}
