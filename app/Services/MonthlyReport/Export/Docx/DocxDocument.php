<?php

namespace App\Services\MonthlyReport\Export\Docx;

use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Thin, purpose-built wrapper around PHPWord for the monthly report DOCX export.
 * Owns document-wide styling (fonts, table shading/borders, header/footer) so the
 * exporter itself only describes report content, not PHPWord mechanics.
 */
final class DocxDocument
{
    private const HEADER_FILL = 'D9EAD3';

    private const TABLE_STYLE = [
        'borderSize' => 8,
        'borderColor' => '000000',
        'cellMargin' => 40,
    ];

    private const A4_PORTRAIT_WIDTH_TWIPS = 11906;

    private const A4_LANDSCAPE_WIDTH_TWIPS = 16838;

    private PhpWord $phpWord;

    private ?Section $section = null;

    private array $meta;

    /** @var array<int, string> temp image files to remove after save() */
    private array $tempFiles = [];

    private int $contentWidthTwips = self::A4_PORTRAIT_WIDTH_TWIPS - (2 * 1134);

    public function __construct(array $meta)
    {
        $this->meta = $meta;

        // PHPWord writes text raw by default; report data ("M&E", "< 100mm") must be escaped
        // or the resulting document.xml is invalid and Word refuses to open the file.
        Settings::setOutputEscapingEnabled(true);

        $phpWord = new PhpWord;
        $phpWord->getSettings()->setUpdateFields(true);
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(9);

        $phpWord->addTitleStyle(1, ['bold' => true, 'size' => 11, 'name' => 'Arial'], ['spaceBefore' => 120, 'spaceAfter' => 60]);
        $phpWord->addTitleStyle(2, ['bold' => true, 'size' => 10, 'name' => 'Arial'], ['spaceBefore' => 100, 'spaceAfter' => 60]);

        $this->phpWord = $phpWord;
    }

    public function newSection(string $orientation = 'portrait'): void
    {
        $orientation = $orientation === 'landscape' ? 'landscape' : 'portrait';

        $margin = (int) round(Converter::cmToTwip(2.0)); // 20mm
        $marginTop = (int) round(Converter::cmToTwip(2.6)); // 26mm, room for the header

        $settings = [
            'marginLeft' => $margin,
            'marginRight' => $margin,
            'marginTop' => $marginTop,
            'marginBottom' => $margin,
        ];
        if ($orientation === 'landscape') {
            $settings['orientation'] = 'landscape';
        }

        $pageWidth = $orientation === 'landscape' ? self::A4_LANDSCAPE_WIDTH_TWIPS : self::A4_PORTRAIT_WIDTH_TWIPS;
        $this->contentWidthTwips = $pageWidth - (2 * $margin);

        $this->section = $this->phpWord->addSection($settings);

        $this->addHeaderFooter($this->section);
    }

    public function heading(string $text, int $level = 1): void
    {
        $this->requireSection()->addTitle($text, $level);
    }

    public function paragraph(string $text, array $style = [], array $paragraphStyle = []): void
    {
        $this->requireSection()->addText($text, array_merge(['size' => 9], $style), $paragraphStyle);
    }

    /**
     * Writes one paragraph made of several differently-styled runs on the same line, e.g. a
     * bold name followed by a plain designation (used by OrgChartWriter).
     *
     * @param  array<int, array{text: string, style?: array}>  $runs
     */
    public function paragraphRuns(array $runs, array $paragraphStyle = []): void
    {
        $run = $this->requireSection()->addTextRun($paragraphStyle);
        foreach ($runs as $r) {
            $run->addText((string) ($r['text'] ?? ''), array_merge(['size' => 9], $r['style'] ?? []));
        }
    }

    public function note(?string $text): void
    {
        if ($text === null || trim($text) === '') {
            return;
        }

        $this->requireSection()->addText($text, ['italic' => true, 'color' => '808080', 'size' => 8]);
    }

    public function toc(): void
    {
        $section = $this->requireSection();
        $section->addTOC(['size' => 9], ['tocTitle' => null]);
        $section->addText(
            'Right-click → Update Field (or press F9) to refresh the table of contents.',
            ['italic' => true, 'size' => 8, 'color' => '808080']
        );
    }

    public function pageBreak(): void
    {
        $this->requireSection()->addPageBreak();
    }

    /**
     * @param  string[]|null  $headers  Column headers, or null for no header row.
     * @param  array<int, array<int, mixed>>  $rows  Rows of cells (string|int|float|null, or
     *                                               ['text' => ..., 'colspan' => n]).
     * @param  array  $opts  ... 'emptyAs' (default '-'): text substituted for a null/''/[] cell
     *                       value — pass '' (or a non-breaking space) for a grid where a blank
     *                       cell should stay visually blank instead of showing a dash.
     */
    public function table(?array $headers, array $rows, array $opts = []): void
    {
        $fontSize = $opts['fontSize'] ?? 8;
        $widths = $opts['widths'] ?? [];
        $align = $opts['align'] ?? [];
        $repeatHeader = $opts['repeatHeader'] ?? true;
        $shading = $opts['shading'] ?? null;
        $boldRows = $opts['bold'] ?? [];
        $emptyAs = array_key_exists('emptyAs', $opts) ? (string) $opts['emptyAs'] : '-';

        $table = $this->requireSection()->addTable(self::TABLE_STYLE);

        if ($headers !== null && $headers !== []) {
            $table->addRow(null, $repeatHeader ? ['tblHeader' => true] : []);
            foreach ($headers as $c => $head) {
                $cell = $table->addCell($this->cellWidth($widths[$c] ?? null), ['bgColor' => self::HEADER_FILL]);
                $cell->addText((string) $head, ['bold' => true, 'size' => $fontSize], ['alignment' => $this->alignmentFor($align[$c] ?? null)]);
            }
        }

        foreach ($rows as $r => $row) {
            $table->addRow();
            $c = 0;
            foreach ($row as $cellData) {
                $colspan = 1;
                $value = $cellData;
                if (is_array($cellData) && array_key_exists('text', $cellData)) {
                    $value = $cellData['text'];
                    $colspan = (int) ($cellData['colspan'] ?? 1);
                }

                $cellStyle = [];
                $fill = $shading ? $shading($r, $c) : null;
                if ($fill) {
                    $cellStyle['bgColor'] = $fill;
                }
                if ($colspan > 1) {
                    $cellStyle['gridSpan'] = $colspan;
                }

                $cell = $table->addCell($this->cellWidth($widths[$c] ?? null), $cellStyle);
                $this->addCellLines(
                    $cell,
                    $value,
                    ['bold' => in_array($r, $boldRows, true), 'size' => $fontSize],
                    ['alignment' => $this->alignmentFor($align[$c] ?? null)],
                    $emptyAs
                );

                $c += $colspan;
            }
        }
    }

    public function keyValue(array $pairs): void
    {
        $table = $this->requireSection()->addTable(self::TABLE_STYLE);

        foreach ($pairs as $label => $value) {
            $table->addRow();
            $table->addCell((int) round($this->contentWidthTwips * 0.35))->addText((string) $label, ['bold' => true, 'size' => 9]);
            $this->addCellLines($table->addCell((int) round($this->contentWidthTwips * 0.65)), $value, ['size' => 9]);
        }
    }

    public function image(string $binary, array $opts = []): void
    {
        $tmp = $this->tempImageFile($binary);

        $imageOpts = ['alignment' => $opts['align'] ?? Jc::CENTER];

        $widthMm = $opts['width'] ?? null;
        $widthTwips = $widthMm !== null ? (int) round(Converter::cmToTwip($widthMm / 10)) : $this->contentWidthTwips;
        $imageOpts['width'] = $this->pixelsFromTwips($widthTwips);

        $this->requireSection()->addImage($tmp, $imageOpts);
    }

    /**
     * Lays out images (with optional captions) in an evenly-spaced grid table, N per row.
     *
     * @param  array<int, array{image: ?string, caption?: ?string}>  $items  raw binary or null
     */
    public function imageGrid(array $items, array $opts = []): void
    {
        if ($items === []) {
            return;
        }

        $columns = max(1, (int) ($opts['columns'] ?? 2));
        $widthMm = (float) ($opts['widthMm'] ?? 80);
        $colWidth = (int) round($this->contentWidthTwips / $columns);

        $table = $this->requireSection()->addTable(self::TABLE_STYLE);

        foreach (array_chunk($items, $columns) as $rowItems) {
            $table->addRow();
            foreach ($rowItems as $item) {
                $cell = $table->addCell($colWidth);
                $this->addImageToCell($cell, $item['image'] ?? null, $widthMm);
                $cell->addText((string) ($item['caption'] ?? ''), ['size' => 8], ['alignment' => Jc::CENTER]);
            }
            for ($i = count($rowItems); $i < $columns; $i++) {
                $table->addCell($colWidth);
            }
        }
    }

    /**
     * Renders a Previous | Current comparison table, one row per pair (used by 5.0).
     *
     * @param  array<int, array{label: string, previous: ?array{image: ?string, caption?: ?string}, current: ?array{image: ?string, caption?: ?string}}>  $pairs
     */
    public function imagePairs(array $pairs, array $opts = []): void
    {
        if ($pairs === []) {
            return;
        }

        $widthMm = (float) ($opts['widthMm'] ?? 75);
        $half = (int) round($this->contentWidthTwips / 2);

        $table = $this->requireSection()->addTable(self::TABLE_STYLE);

        $table->addRow(null, ['tblHeader' => true]);
        foreach (['Previous', 'Current'] as $head) {
            $table->addCell($half, ['bgColor' => self::HEADER_FILL])->addText($head, ['bold' => true, 'size' => 8]);
        }

        foreach ($pairs as $pair) {
            $table->addRow();
            foreach (['previous', 'current'] as $slot) {
                $cell = $table->addCell($half);
                $cell->addText((string) ($pair['label'] ?? ''), ['bold' => true, 'size' => 8]);

                $side = $pair[$slot] ?? null;
                if ($side && ! empty($side['image'])) {
                    $this->addImageToCell($cell, $side['image'], $widthMm);
                    $cell->addText((string) ($side['caption'] ?? ''), ['size' => 8]);
                } else {
                    $cell->addText('No image.', ['italic' => true, 'size' => 8, 'color' => '555555']);
                }
            }
        }
    }

    /** Decodes a `data:<mime>;base64,<data>` URI (as produced by ReportViewData) to raw binary, or null. */
    public static function binaryFromDataUri(?string $dataUri): ?string
    {
        if ($dataUri === null) {
            return null;
        }

        $comma = strpos($dataUri, ',');
        if ($comma === false) {
            return null;
        }

        return base64_decode(substr($dataUri, $comma + 1)) ?: null;
    }

    public function save(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        try {
            IOFactory::createWriter($this->phpWord, 'Word2007')->save($tmp);

            return (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
            foreach ($this->tempFiles as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            $this->tempFiles = [];
        }
    }

    private function addHeaderFooter(Section $section): void
    {
        $header = $section->addHeader();
        $header->addText((string) ($this->meta['title'] ?? ''), ['bold' => true, 'size' => 10]);

        $subtitle = trim(
            ($this->meta['project_title'] ?? '').
            (! empty($this->meta['contract_no']) ? '  —  '.$this->meta['contract_no'] : '')
        );
        if ($subtitle !== '') {
            $header->addText($subtitle, ['size' => 8]);
        }

        $footer = $section->addFooter();
        $footer->addPreserveText('Page {PAGE} of {NUMPAGES}', ['size' => 8], ['alignment' => Jc::CENTER]);
    }

    private function tempImageFile(string $binary): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docximg');
        file_put_contents($tmp, $binary);
        $this->tempFiles[] = $tmp;

        return $tmp;
    }

    // 1 twip = 1/20 point; Converter has no twip->pixel helper, so go via points.
    private function pixelsFromTwips(int $twips): float
    {
        return Converter::pointToPixel($twips / 20);
    }

    private function addImageToCell(Cell $cell, ?string $binary, float $widthMm): void
    {
        if ($binary === null) {
            return;
        }

        $tmp = $this->tempImageFile($binary);
        $widthTwips = (int) round(Converter::cmToTwip($widthMm / 10));

        $cell->addImage($tmp, ['width' => $this->pixelsFromTwips($widthTwips), 'alignment' => Jc::CENTER]);
    }

    private function requireSection(): Section
    {
        if ($this->section === null) {
            $this->newSection('portrait');
        }

        return $this->section;
    }

    /**
     * Writes $value into $cell as one or more paragraphs: an array of strings becomes one
     * paragraph per item, and any item (or a plain string) containing "\n" is split into one
     * paragraph per line — so newlines never leak into document.xml as a raw literal "\n".
     */
    private function addCellLines(Cell $cell, mixed $value, array $fontStyle, array $paragraphStyle = [], string $emptyAs = '-'): void
    {
        foreach ($this->cellLines($value, $emptyAs) as $line) {
            $cell->addText($line, $fontStyle, $paragraphStyle);
        }
    }

    /** @return string[] non-empty list of plain-text lines */
    private function cellLines(mixed $value, string $emptyAs = '-'): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [$emptyAs];
        }

        $items = is_array($value) ? $value : [$value];

        $lines = [];
        foreach ($items as $item) {
            foreach (explode("\n", (string) $item) as $line) {
                $lines[] = $line;
            }
        }

        return $lines === [] ? ['-'] : $lines;
    }

    private function cellWidth(int|string|null $width): ?int
    {
        if ($width === null) {
            return null;
        }

        if (is_string($width) && str_ends_with($width, '%')) {
            $percent = (float) rtrim($width, '%');

            return (int) round($this->contentWidthTwips * ($percent / 100));
        }

        return (int) $width;
    }

    private function alignmentFor(?string $align): string
    {
        return match ($align) {
            'r' => Jc::END,
            'c' => Jc::CENTER,
            default => Jc::START,
        };
    }
}
