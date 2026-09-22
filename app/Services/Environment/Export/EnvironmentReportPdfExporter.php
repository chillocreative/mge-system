<?php

namespace App\Services\Environment\Export;

use App\Models\EnvironmentReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;

class EnvironmentReportPdfExporter
{
    public function pdf(EnvironmentReport $report): DomPdfWrapper
    {
        $data = EnvironmentReportViewData::build($report);

        $html = view('pdf.environment-report.layout', $data)->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        $pdf->render();

        $this->stampPageNumbers($pdf);

        return $pdf;
    }

    /** Stamps "Page X of Y" bottom-right on every page using DomPDF's canvas API. */
    private function stampPageNumbers(DomPdfWrapper $pdf): void
    {
        $domPdf = $pdf->getDomPDF();
        $canvas = $domPdf->getCanvas();
        $font = $domPdf->getFontMetrics()->getFont('DejaVu Sans');

        $canvas->page_text(
            $canvas->get_width() - 90,
            $canvas->get_height() - 40,
            'Page {PAGE_NUM} of {PAGE_COUNT}',
            $font,
            8,
            [0.1, 0.1, 0.1]
        );
    }
}
