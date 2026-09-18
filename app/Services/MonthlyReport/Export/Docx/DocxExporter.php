<?php

namespace App\Services\MonthlyReport\Export\Docx;

use App\Models\MonthlyReport;
use App\Services\MonthlyReport\Export\Docx\Writers\ChartWriter;
use App\Services\MonthlyReport\Export\Docx\Writers\CoverWriter;
use App\Services\MonthlyReport\Export\Docx\Writers\GanttWriter;
use App\Services\MonthlyReport\Export\Docx\Writers\GroupsNumbersWriter;
use App\Services\MonthlyReport\Export\Docx\Writers\GroupsRowsWriter;
use App\Services\MonthlyReport\Export\Docx\Writers\ImagesWriter;
use App\Services\MonthlyReport\Export\Docx\Writers\KeyValueWriter;
use App\Services\MonthlyReport\Export\Docx\Writers\MatrixWriter;
use App\Services\MonthlyReport\Export\Docx\Writers\OrgChartWriter;
use App\Services\MonthlyReport\Export\Docx\Writers\ProgressWriter;
use App\Services\MonthlyReport\Export\Docx\Writers\RowsWriter;
use App\Services\MonthlyReport\Export\Docx\Writers\SectionWriter;
use App\Services\MonthlyReport\Export\Docx\Writers\TextWriter;
use App\Services\MonthlyReport\Export\Docx\Writers\WeatherWriter;
use App\Services\MonthlyReport\Export\OrientationPlanner;
use App\Services\MonthlyReport\Export\ReportViewData;
use App\Services\MonthlyReport\SectionRegistry;
use Illuminate\Validation\ValidationException;

/**
 * Builds the DOCX export of a monthly report: a cover page, a table of contents, then every
 * included section in the same portrait/landscape chunking as the PDF export. Mirrors
 * PdfExporter::render() but writes directly into a DocxDocument instead of rendering Blade/HTML.
 */
final class DocxExporter
{
    /** @var array<string, class-string<SectionWriter>> */
    public const WRITERS = [
        'cover' => CoverWriter::class,
        '1.1' => KeyValueWriter::class,
        '1.2' => RowsWriter::class,
        '1.3' => ImagesWriter::class,
        '1.4' => OrgChartWriter::class,
        '1.5' => RowsWriter::class,
        '2.1' => ProgressWriter::class,
        '2.2' => ChartWriter::class,
        '2.3' => RowsWriter::class,
        '2.4' => ChartWriter::class,
        '2.5' => GanttWriter::class,
        '2.6' => RowsWriter::class,
        '3.1' => GroupsNumbersWriter::class,
        '3.2' => GroupsRowsWriter::class,
        '3.4' => RowsWriter::class,
        '3.6' => RowsWriter::class,
        '3.7' => RowsWriter::class,
        '4.1' => MatrixWriter::class,
        '4.2' => MatrixWriter::class,
        '4.3' => WeatherWriter::class,
        '5.0' => ImagesWriter::class,
    ];

    public function render(MonthlyReport $report): string
    {
        $report->loadMissing(['sections', 'project', 'period']);

        $keys = $report->sections->where('include', true)->sortBy('sort_order')->pluck('key')->values()->all();

        if ($keys === []) {
            throw ValidationException::withMessages(['sections' => 'Include at least one section before exporting.']);
        }

        $data = ReportViewData::build($report);
        $chunks = OrientationPlanner::plan($keys, $report->options['landscape_sections'] ?? null);

        $doc = new DocxDocument($this->meta($data));

        $writerCtx = [
            'logos' => $data['logos'],
            'mgeLogo' => $data['mgeLogo'],
            'ctx' => $data['ctx'],
            'report' => $report,
        ];

        foreach ($chunks as $index => $chunk) {
            $doc->newSection($chunk['orientation']);
            $writerCtx['orientation'] = $chunk['orientation'];

            $chunkKeys = $chunk['keys'];

            if ($index === 0) {
                if (in_array('cover', $chunkKeys, true)) {
                    $this->write($doc, 'cover', $data['sections']['cover'] ?? ['placeholder' => true], $data['notes']['cover'] ?? null, $writerCtx);
                    $doc->pageBreak();
                }

                $doc->toc();
                $doc->pageBreak();

                $chunkKeys = array_values(array_filter($chunkKeys, fn ($key) => $key !== 'cover'));
            }

            foreach ($chunkKeys as $i => $key) {
                if ($i > 0) {
                    $doc->pageBreak();
                }

                $doc->heading(SectionRegistry::TITLES[$key] ?? $key);

                $sectionData = $data['sections'][$key] ?? ['placeholder' => true];
                $note = $data['notes'][$key] ?? null;
                $this->write($doc, $key, $sectionData, $note, $writerCtx);
                $doc->note($note);
            }
        }

        return $doc->save();
    }

    private function write(DocxDocument $doc, string $key, array $data, ?string $note, array $ctx): void
    {
        $class = self::WRITERS[$key] ?? TextWriter::class;
        (new $class)->write($doc, $key, $data, $note, $ctx);
    }

    private function meta(array $data): array
    {
        $ctx = $data['ctx'];

        return [
            'title' => "Monthly Progress Report No.{$ctx->reportNo}",
            'project_title' => $ctx->project->name,
            'contract_no' => $ctx->contract?->contract_no ?? $ctx->project->code,
            'client_logo' => DocxDocument::binaryFromDataUri($data['logos']['owner'] ?? null),
            'mge_logo' => DocxDocument::binaryFromDataUri($data['mgeLogo'] ?? null),
        ];
    }
}
