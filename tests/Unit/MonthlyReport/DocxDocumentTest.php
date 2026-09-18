<?php

namespace Tests\Unit\MonthlyReport;

use App\Services\MonthlyReport\Export\Docx\DocxDocument;
use PHPUnit\Framework\TestCase;

class DocxDocumentTest extends TestCase
{
    public function test_builds_document_with_two_orientations_table_and_toc(): void
    {
        $doc = new DocxDocument(['title' => 'Monthly Progress Report No.9', 'project_title' => 'RTB SG.MUAR', 'contract_no' => 'JPS/1']);
        $doc->newSection('portrait');
        $doc->toc();
        $doc->heading('1.1 PROJECT INFORMATION');
        $doc->table(['Item', 'Value'], [['Contract Sum', 'RM 288,000,000.00'], ['DLP', null]]);
        $doc->note('Handwritten note');
        $doc->newSection('landscape');
        $doc->heading('4.1 MONTHLY TRADE WORKER');
        $doc->table(['No.', 'Desc', '1', '2'], [[1, 'GW', 3, null]], ['shading' => fn ($r, $c) => $c === 2 ? '60A5FA' : null]);

        $bytes = $doc->save();
        $xml = $this->documentXml($bytes);

        $this->assertStringStartsWith('PK', $bytes);
        $this->assertStringContainsString('w:orient="landscape"', $xml);
        $this->assertStringContainsString('TOC \o', $xml);
        $this->assertStringContainsString('1.1 PROJECT INFORMATION', $xml);
        $this->assertStringContainsString('w:fill="D9EAD3"', $xml);
        $this->assertStringContainsString('w:fill="60A5FA"', $xml);
        $this->assertStringContainsString('Handwritten note', $xml);

        $footerXml = $this->anyPartXmlMatching($bytes, '#^word/footer\d+\.xml$#');
        $this->assertStringContainsString('NUMPAGES', $footerXml);

        $headerXml = $this->anyPartXmlMatching($bytes, '#^word/header\d+\.xml$#');
        $this->assertStringContainsString('RTB SG.MUAR', $headerXml);
    }

    public function test_escapes_xml_special_characters_in_text(): void
    {
        $doc = new DocxDocument(['title' => 'R', 'project_title' => 'M&E <Works>', 'contract_no' => 'C']);
        $doc->newSection();
        $doc->heading('1.2 M&E <Works>');
        $doc->table(['Item'], [['< 100mm & "quoted"']]);

        $bytes = $doc->save();
        $xml = $this->documentXml($bytes);

        $this->assertStringContainsString('M&amp;E &lt;Works&gt;', $xml);
        $this->assertStringContainsString('&lt; 100mm &amp;', $xml);
        $dom = new \DOMDocument;
        $this->assertTrue($dom->loadXML($xml), 'document.xml must be well-formed');
    }

    public function test_table_and_key_value_cells_render_multiple_lines_without_raw_newlines(): void
    {
        $doc = new DocxDocument(['title' => 'R', 'project_title' => 'P', 'contract_no' => 'C']);
        $doc->newSection();
        $doc->table(['Contacts'], [
            [['Alice — Engineer', 'Bob — Manager']],
        ]);
        $doc->keyValue(['Note' => "Line one\nLine two"]);

        $bytes = $doc->save();
        $xml = $this->documentXml($bytes);

        preg_match_all('#<w:t(?:\s[^>]*)?>(.*?)</w:t>#s', $xml, $matches);
        $this->assertContains('Alice — Engineer', $matches[1], 'Each contact line must be its own <w:t> run');
        $this->assertContains('Bob — Manager', $matches[1], 'Each contact line must be its own <w:t> run');
        $this->assertContains('Line one', $matches[1], 'Each split line must be its own <w:t> run');
        $this->assertContains('Line two', $matches[1], 'Each split line must be its own <w:t> run');
        $this->assertStringNotContainsString('\n', $xml, 'A literal backslash-n must never leak into document.xml');
    }

    public function test_image_width_is_in_points_not_pixels_and_fits_landscape_content_width(): void
    {
        $doc = new DocxDocument(['title' => 'R', 'project_title' => 'P', 'contract_no' => 'C']);
        $doc->newSection('landscape');
        $doc->image($this->tinyPng(), ['width' => 170]);

        $xml = $this->documentXml($doc->save());

        $this->assertMatchesRegularExpression('/style="[^"]*width:([0-9.]+)pt/', $xml);
        preg_match('/style="[^"]*width:([0-9.]+)pt/', $xml, $m);
        $widthPt = (float) $m[1];

        // 170mm * 72/25.4 ≈ 481.9pt.
        $this->assertEqualsWithDelta(481.9, $widthPt, 1.0);

        // Landscape A4 content width: 16838 - 2*1134 twips = 14570 twips = 728.5pt.
        $this->assertLessThanOrEqual(728.5, $widthPt);
    }

    public function test_toc_depth_is_limited_to_level_one(): void
    {
        $doc = new DocxDocument(['title' => 'R', 'project_title' => 'P', 'contract_no' => 'C']);
        $doc->newSection();
        $doc->toc();
        $doc->heading('1.1 PROJECT INFORMATION');

        $xml = $this->documentXml($doc->save());

        $this->assertStringContainsString('TOC \o 1-1', $xml);
    }

    public function test_header_and_footer_render_logo_title_and_page_fields(): void
    {
        $doc = new DocxDocument([
            'title' => 'Monthly Progress Report No.9',
            'project_title' => 'RTB SG.MUAR',
            'contract_no' => 'JPS/1',
            'client_logo' => $this->tinyPng(),
            'mge_logo' => $this->tinyPng(),
        ]);
        $doc->newSection();
        $doc->heading('1.1 PROJECT INFORMATION');

        $bytes = $doc->save();

        $headerXml = $this->anyPartXmlMatching($bytes, '#^word/header\d+\.xml$#');
        $this->assertStringContainsString('RTB SG.MUAR', $headerXml);
        $this->assertStringContainsString('JPS/1', $headerXml);
        $this->assertStringContainsString('<w:pict>', $headerXml, 'both logos must be embedded as images');

        $footerXml = $this->anyPartXmlMatching($bytes, '#^word/footer\d+\.xml$#');
        $this->assertStringContainsString('Monthly Progress Report No.9', $footerXml);
        $this->assertStringContainsString('NUMPAGES', $footerXml);
    }

    public function test_header_skips_missing_logo_without_error(): void
    {
        $doc = new DocxDocument(['title' => 'R', 'project_title' => 'P', 'contract_no' => 'C']);
        $doc->newSection();
        $doc->heading('X');

        $bytes = $doc->save();

        $this->assertStringStartsWith('PK', $bytes);
        $headerXml = $this->anyPartXmlMatching($bytes, '#^word/header\d+\.xml$#');
        $this->assertStringContainsString('P', $headerXml);
    }

    public function test_invalid_image_binary_falls_back_to_placeholder_and_leaves_no_temp_files(): void
    {
        $before = glob(sys_get_temp_dir().'/docximg*');

        $doc = new DocxDocument(['title' => 'R', 'project_title' => 'P', 'contract_no' => 'C']);
        $doc->newSection();
        $doc->image('not a real image, just garbage bytes');

        $xml = $this->documentXml($doc->save());

        $this->assertStringContainsString('Image could not be embedded.', $xml);
        $this->assertStringNotContainsString('<w:pict>', $xml);

        $after = glob(sys_get_temp_dir().'/docximg*');
        $this->assertSame($before, $after, 'no leaked docximg* temp files');
    }

    public function test_page_break_on_a_fresh_section_is_a_no_op(): void
    {
        $doc = new DocxDocument(['title' => 'R', 'project_title' => 'P', 'contract_no' => 'C']);
        $doc->newSection();
        $doc->pageBreak(); // nothing written yet — must not emit a break

        $xml = $this->documentXml($doc->save());

        $this->assertStringNotContainsString('<w:br w:type="page"/>', $xml);
    }

    private function tinyPng(): string
    {
        if (function_exists('imagecreatetruecolor')) {
            $img = imagecreatetruecolor(2, 2);
            ob_start();
            imagepng($img);
            $bytes = ob_get_clean();
            imagedestroy($img);

            return $bytes;
        }

        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }

    private function documentXml(string $bytes): string
    {
        return $this->partXml($bytes, 'word/document.xml');
    }

    private function partXml(string $bytes, string $part): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($tmp, $bytes);
        $zip = new \ZipArchive;
        $zip->open($tmp);
        $xml = (string) $zip->getFromName($part);
        $zip->close();
        unlink($tmp);

        return $xml;
    }

    private function anyPartXmlMatching(string $bytes, string $pattern): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($tmp, $bytes);
        $zip = new \ZipArchive;
        $zip->open($tmp);

        $combined = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== null && preg_match($pattern, $name)) {
                $combined .= (string) $zip->getFromName($name);
            }
        }
        $zip->close();
        unlink($tmp);

        return $combined;
    }
}
