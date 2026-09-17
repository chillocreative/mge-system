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

    /** FPDF writes text uncompressed in a Tj operator; decode by stripping the stream to visible text. */
    private function decodeText(string $pdf): string
    {
        return implode(' ', array_map(fn ($m) => $m[1], iterator_to_array((function () use ($pdf) {
            preg_match_all('/\((.*?)\)\s*Tj/s', $pdf, $ms, PREG_SET_ORDER);
            yield from $ms;
        })())));
    }
}
