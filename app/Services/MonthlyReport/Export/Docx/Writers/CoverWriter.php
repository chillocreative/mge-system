<?php

namespace App\Services\MonthlyReport\Export\Docx\Writers;

use App\Services\MonthlyReport\Export\Docx\DocxDocument;

/**
 * Renders the report cover page, mirroring cover.blade.php: report title, project/contract/
 * period key facts, one row per party (client/SO/consultant/contractor) with company + address,
 * each party's logo where available, and the 3-column Prepared/Verified/Accepted signatory table.
 */
final class CoverWriter implements SectionWriter
{
    /** @var array<string, array{label: string, logoRole: string}> */
    private const PARTY_SLOTS = [
        'client' => ['label' => 'Client', 'logoRole' => 'owner'],
        'so' => ['label' => 'Superintending Officer', 'logoRole' => 'superintending_officer'],
        'consultant' => ['label' => 'Consultant', 'logoRole' => 'consultant'],
        'contractor' => ['label' => 'Contractor', 'logoRole' => 'contractor'],
    ];

    public function write(DocxDocument $doc, string $key, array $data, ?string $note, array $ctx): void
    {
        if (empty($data) || ! empty($data['placeholder'])) {
            $doc->paragraph('No data.', ['italic' => true, 'color' => '555555']);

            return;
        }

        $doc->heading(
            'MONTHLY PROGRESS REPORT NO.'.($data['report_no'] ?? '').' ('.($data['report_no_words'] ?? '').')',
            1
        );

        $keyFacts = [
            'Project Title' => $data['project_title'] ?? '',
            'Contract No.' => $data['contract_no'] ?? '',
            'Reporting Period' => $data['period_label'] ?? '',
        ];
        if (! empty($data['evaluation_date'])) {
            $keyFacts['Evaluation Date'] = $data['evaluation_date'];
        }
        $doc->keyValue($keyFacts);

        $rows = [];
        foreach (self::PARTY_SLOTS as $slot => $meta) {
            $party = $data[$slot] ?? null;
            $rows[] = [$meta['label'], $party['name'] ?? '-', $party['address'] ?? '-'];
        }
        $doc->table(['Role', 'Company', 'Address'], $rows);

        $logos = $ctx['logos'] ?? [];
        foreach (self::PARTY_SLOTS as $slot => $meta) {
            $uri = $logos[$meta['logoRole']] ?? null;
            $binary = DocxDocument::binaryFromDataUri($uri);
            if ($binary !== null) {
                $doc->paragraph($meta['label'].' logo:', ['bold' => true, 'size' => 8]);
                $doc->image($binary, ['width' => 30]);
            }
        }

        $signatories = collect($data['signatories'] ?? []);
        $row = [];
        foreach (['prepared', 'verified', 'accepted'] as $slot) {
            $sig = $signatories->firstWhere('slot', $slot) ?? [];
            $row[] = [
                'Name: '.($sig['name'] ?? ''),
                'Designation: '.($sig['designation'] ?? ''),
                'Company: '.($sig['company'] ?? ''),
            ];
        }
        $doc->table(['Prepared By', 'Verified By', 'Accepted By'], [$row]);
    }
}
