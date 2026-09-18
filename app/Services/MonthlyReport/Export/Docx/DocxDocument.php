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

    /** True once something has been written into the currently open section. */
    private bool $sectionHasContent = false;

    /** Orientation of the currently open section (only meaningful once $section !== null). */
    private string $currentOrientation = 'portrait';

    /**
     * Orientation requested via ensureSection() but not yet materialised into an actual
     * PHPWord section — the section is only opened lazily, the next time content is written,
     * so a "restore orientation" request that nothing ends up writing into never produces an
     * empty section/page.
     */
    private ?string $pendingOrientation = null;

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
        $this->contentWidthTwips = (int) $pageWidth - (2 * $margin);

        $this->section = $this->phpWord->addSection($settings);
        $this->sectionHasContent = false;
        $this->currentOrientation = $orientation;
        $this->pendingOrientation = null;

        $this->addHeaderFooter($this->section);
    }

    /**
     * Requests that the given orientation be in effect the next time content is written, without
     * opening a section immediately. Used to "restore" the chunk orientation after a writer (e.g.
     * GanttWriter) opens its own mid-chunk landscape section(s): if nothing is ever written after
     * the request, no section is materialised, so no blank page is emitted. A no-op when the
     * currently open section is already in the requested orientation.
     */
    public function ensureSection(string $orientation): void
    {
        $orientation = $orientation === 'landscape' ? 'landscape' : 'portrait';

        if ($this->section !== null && $this->currentOrientation === $orientation) {
            $this->pendingOrientation = null;

            return;
        }

        $this->pendingOrientation = $orientation;
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
        $section->addTOC(['size' => 9], ['tocTitle' => null], 1, 1);
        $section->addText(
            'Right-click → Update Field (or press F9) to refresh the table of contents.',
            ['italic' => true, 'size' => 8, 'color' => '808080']
        );
    }

    public function pageBreak(): void
    {
        // A no-op on a section that has no content yet (including one only "requested" via
        // ensureSection() and not yet materialised) — a page break can never be the first thing
        // in a section, or Word renders an extra blank page before it.
        if ($this->pendingOrientation !== null || $this->section === null || ! $this->sectionHasContent) {
            return;
        }

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

        if (! $this->isEmbeddableImage($tmp)) {
            $this->discardTempFile($tmp);
            $this->paragraph('Image could not be embedded.', ['italic' => true, 'color' => '808080']);

            return;
        }

        $imageOpts = [
            'alignment' => $opts['align'] ?? Jc::CENTER,
            'width' => $this->imageWidthPoints($opts['width'] ?? null),
        ];

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
            $this->cleanupTempFiles();
        }
    }

    /**
     * Defense-in-depth: if rendering throws before save() runs (e.g. a writer error), the
     * temp image files created so far are still cleaned up when this object is destructed.
     */
    public function __destruct()
    {
        $this->cleanupTempFiles();
    }

    private function cleanupTempFiles(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];
    }

    /**
     * Header: a borderless 3-cell table — client logo | project title + contract no (centred) |
     * MGE logo — a cell's image is skipped when its binary is null/invalid (addImageToCell
     * guards that). Footer: a borderless 2-cell table — the report title left, "Page N of M"
     * right.
     */
    private function addHeaderFooter(Section $section): void
    {
        $borderless = ['borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 0];

        $header = $section->addHeader();
        $logoWidth = (int) round($this->contentWidthTwips * 0.18);
        $centerWidth = $this->contentWidthTwips - (2 * $logoWidth);

        $headerTable = $header->addTable($borderless);
        $headerTable->addRow();

        $this->addImageToCell($headerTable->addCell($logoWidth), $this->meta['client_logo'] ?? null, 14);

        $centerCell = $headerTable->addCell($centerWidth);
        $centerCell->addText((string) ($this->meta['project_title'] ?? ''), ['bold' => true, 'size' => 10], ['alignment' => Jc::CENTER]);
        if (! empty($this->meta['contract_no'])) {
            $centerCell->addText((string) $this->meta['contract_no'], ['size' => 8], ['alignment' => Jc::CENTER]);
        }

        $this->addImageToCell($headerTable->addCell($logoWidth), $this->meta['mge_logo'] ?? null, 14);

        $footer = $section->addFooter();
        $half = (int) round($this->contentWidthTwips / 2);

        $footerTable = $footer->addTable($borderless);
        $footerTable->addRow();
        $footerTable->addCell($half)->addText((string) ($this->meta['title'] ?? ''), ['size' => 8]);
        $footerTable->addCell($half)->addPreserveText('Page {PAGE} of {NUMPAGES}', ['size' => 8], ['alignment' => Jc::END]);
    }

    private function tempImageFile(string $binary): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docximg');
        file_put_contents($tmp, $binary);
        $this->tempFiles[] = $tmp;

        return $tmp;
    }

    /**
     * PHPWord's Style\Image width/height are in points, not pixels — 1mm = 72/25.4 points.
     * A null width falls back to the full content width (already tracked in points-equivalent
     * twips, so /20 converts twips to points).
     */
    private function imageWidthPoints(?float $widthMm): float
    {
        if ($widthMm !== null) {
            return $widthMm * 72 / 25.4;
        }

        return $this->contentWidthTwips / 20;
    }

    /** @param  mixed  $info  getimagesize() result, kept mixed since it's typed false|array upstream */
    private function isEmbeddableImage(string $path): bool
    {
        $info = @getimagesize($path);

        return is_array($info) && in_array($info[2] ?? null, [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF], true);
    }

    private function discardTempFile(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
        $this->tempFiles = array_values(array_filter($this->tempFiles, fn ($file) => $file !== $path));
    }

    private function addImageToCell(Cell $cell, ?string $binary, float $widthMm): void
    {
        if ($binary === null) {
            return;
        }

        $tmp = $this->tempImageFile($binary);

        if (! $this->isEmbeddableImage($tmp)) {
            $this->discardTempFile($tmp);
            $cell->addText('Image could not be embedded.', ['italic' => true, 'size' => 8, 'color' => '808080']);

            return;
        }

        $cell->addImage($tmp, ['width' => $this->imageWidthPoints($widthMm), 'alignment' => Jc::CENTER]);
    }

    private function requireSection(): Section
    {
        if ($this->pendingOrientation !== null) {
            $this->newSection($this->pendingOrientation);
        }

        if ($this->section === null) {
            $this->newSection('portrait');
        }

        $this->sectionHasContent = true;

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
