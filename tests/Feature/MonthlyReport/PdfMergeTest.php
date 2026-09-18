<?php

namespace Tests\Feature\MonthlyReport;

use App\Services\MonthlyReport\Export\PdfMerger;
use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class PdfMergeTest extends TestCase
{
    public function test_merges_portrait_and_landscape_chunks_and_stamps_global_page_numbers(): void
    {
        $a = Pdf::loadHTML('<p>one</p><div style="page-break-before:always">two</div>')->setPaper('a4', 'portrait')->output();
        $b = Pdf::loadHTML('<p>three</p>')->setPaper('a4', 'landscape')->output();

        $bytes = app(PdfMerger::class)->merge([['pdf' => $a], ['pdf' => $b]], 'Monthly Progress Report No.9');

        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertSame(3, preg_match_all('/\/Type\s*\/Page[^s]/', $bytes));
        $this->assertStringContainsString('Page 3 of 3', $this->decodeText($bytes));
        $this->assertStringContainsString('Monthly Progress Report No.9', $this->decodeText($bytes));
    }

    public function test_probe_rejects_unreadable_pdf(): void
    {
        $path = storage_path('app/testing/not-a-pdf.pdf');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, 'hello');

        $this->expectException(\RuntimeException::class);
        app(PdfMerger::class)->probe($path);
    }

    /**
     * FPDF (via FPDI) compresses content streams by default, so the footer text is not
     * directly greppable in the raw PDF bytes. Inflate every FlateDecode stream and pull
     * the Tj operator's text out of the decompressed content.
     */
    private function decodeText(string $pdf): string
    {
        preg_match_all('/(\/Filter\s*\/FlateDecode.*?)stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams, PREG_SET_ORDER);

        $text = '';
        foreach ($streams as $stream) {
            $decoded = @gzuncompress($stream[2]);
            if ($decoded === false) {
                continue;
            }

            preg_match_all('/\((.*?)\)\s*Tj/s', $decoded, $ms, PREG_SET_ORDER);
            foreach ($ms as $m) {
                $text .= $m[1].' ';
            }
        }

        return $text;
    }
}
