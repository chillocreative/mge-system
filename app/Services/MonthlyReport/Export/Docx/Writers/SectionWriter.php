<?php

namespace App\Services\MonthlyReport\Export\Docx\Writers;

use App\Services\MonthlyReport\Export\Docx\DocxDocument;

/**
 * Renders one section's body into the DOCX document. The section heading and the
 * trailing note (report-author remarks) are written by DocxExporter itself around
 * the call to write() — implementations only produce the section's content.
 */
interface SectionWriter
{
    /**
     * @param  array  $data  The section's merged data (ReportViewData::build()'s sections[$key]).
     * @param  string|null  $note  The section's report-author note (informational; DocxExporter
     *                             writes it separately after write() returns).
     * @param  array  $ctx  Shared context: ['logos' => [...data URIs...], 'mgeLogo' => ?string,
     *                      'ctx' => \App\Services\MonthlyReport\ReportContext,
     *                      'report' => \App\Models\MonthlyReport,
     *                      'orientation' => 'portrait'|'landscape' (of the current chunk)].
     */
    public function write(DocxDocument $doc, string $key, array $data, ?string $note, array $ctx): void;
}
