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
