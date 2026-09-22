<?php

namespace App\Services\Environment\Export;

use App\Models\EnvironmentReport;
use App\Services\MonthlyReport\Export\Docx\DocxDocument;

class EnvironmentReportDocxExporter
{
    /**
     * Renders the environment report to a DOCX file and returns the absolute path to a
     * temporary file holding it. The caller is responsible for deleting it after use.
     */
    public function save(EnvironmentReport $report): string
    {
        $data = EnvironmentReportViewData::build($report);

        $doc = new DocxDocument([
            'title' => $data['report_title'],
            'project_title' => $data['project']->name,
            'contract_no' => $data['contract_no'],
            'client_logo' => DocxDocument::binaryFromDataUri($data['logo_so']),
            'mge_logo' => DocxDocument::binaryFromDataUri($data['logo_mge']),
        ]);

        $doc->newSection('portrait');

        $this->writeCover($doc, $data);
        $doc->pageBreak();
        $this->writeToc($doc, $data);
        $doc->pageBreak();
        $this->writeContract($doc, $data);
        $doc->pageBreak();
        $this->writeEms($doc, $data);
        $doc->pageBreak();
        $this->writeIntroduction($doc, $data);
        $this->writeFlowChart($doc, $data);
        $doc->pageBreak();
        $this->writePolicy($doc, $data);
        $doc->pageBreak();
        $this->writeLocation($doc, $data);
        $doc->pageBreak();
        $this->writeParameters($doc, $data);
        $doc->pageBreak();
        $this->writeResults($doc, $data);
        $doc->pageBreak();
        $this->writeBmp($doc, $data);

        $bytes = $doc->save();

        $tmp = tempnam(sys_get_temp_dir(), 'envreport').'.docx';
        file_put_contents($tmp, $bytes);

        return $tmp;
    }

    private function writeCover(DocxDocument $doc, array $data): void
    {
        $doc->paragraph($data['so_name'] ?: 'JABATAN PENGAIRAN DAN SALIRAN NEGERI JOHOR', ['bold' => true, 'size' => 12], ['alignment' => 'center']);
        $doc->paragraph($data['report_title'] ?: ('MONTHLY ENVIRONMENT REPORT NO.'.$data['report_no']), ['bold' => true, 'size' => 14], ['alignment' => 'center']);
        $doc->paragraph('('.$data['period_label'].')', ['bold' => true, 'size' => 11], ['alignment' => 'center']);

        $p = $data['parties'];
        $addr = fn (array $party) => trim(($party['name'] ?? '').(($party['address_lines'] ?? []) ? ("\n".implode("\n", $party['address_lines'])) : ''));

        $doc->keyValue([
            'PROJECT TITLE' => $data['project']->name,
            'CONTRACT NO.' => $data['contract_no'],
            'PROJECT OWNER' => $addr($p['owner']),
            'SUPERINTENDING OFFICER (SO)' => $addr($p['so']),
            'CONSULTANT' => $addr($p['consultant']),
            'CONTRACTOR' => $addr($p['contractor']),
        ]);

        $row = [];
        foreach (['prepared', 'verified', 'accepted'] as $slot) {
            $sig = $data['signatories'][$slot];
            $row[] = [
                'Name: '.$sig['name'],
                'Designation: '.$sig['designation'],
                'Company: '.$sig['company'],
            ];
        }
        $doc->table(['Prepared By', 'Verified By', 'Accepted By'], [$row]);
    }

    private function writeToc(DocxDocument $doc, array $data): void
    {
        $doc->heading('Table of Contents', 1);

        $rows = [];
        foreach (EnvironmentReport::SECTION_KEYS as $key) {
            $rows[] = [EnvironmentReportViewData::SECTION_NUMBERS[$key], EnvironmentReportViewData::SECTION_TITLES[$key]];
            foreach (($data['section_subitems'][$key] ?? []) as $item) {
                $rows[] = [$item['num'], $item['title']];
            }
        }

        $doc->table(['Section', 'Title'], $rows);
    }

    private function heading(DocxDocument $doc, array $data, string $key): void
    {
        $doc->heading(EnvironmentReportViewData::SECTION_NUMBERS[$key].' '.EnvironmentReportViewData::SECTION_TITLES[$key], 1);
    }

    private function writeContract(DocxDocument $doc, array $data): void
    {
        $this->heading($doc, $data, 'contract');
        $doc->heading('1.1 Contract Information', 2);

        $pairs = [];
        foreach ($data['sections']['contract']['rows'] ?? [] as $row) {
            $pairs[$row['label']] = str_replace(' | ', "\n", (string) $row['value']);
        }
        $doc->keyValue($pairs);
    }

    private function writeEms(DocxDocument $doc, array $data): void
    {
        $ems = $data['sections']['ems'];
        $this->heading($doc, $data, 'ems');
        $doc->table(null, [
            ['Name Environment Consultant', $ems['consultant_name'] ?? 'TBA'],
            ['No. Registration', $ems['consultant_reg_no'] ?? 'TBA'],
            ['Name Environment Officer', $ems['officer_name'] ?? 'TBA'],
            ['No. Registration', $ems['officer_reg_no'] ?? 'TBA'],
        ], ['widths' => ['35%', '65%'], 'cellStyle' => fn ($r, $c) => $c === 0 ? ['bold' => true] : []]);

        $doc->heading('2.1 Certified Environment Consultant', 2);
        $doc->paragraph($ems['certified_text'] ?? '');

        $binary = DocxDocument::binaryFromDataUri($data['consultant_cert']);
        if ($binary !== null) {
            $doc->image($binary, ['width' => 100]);
        }
    }

    private function writeIntroduction(DocxDocument $doc, array $data): void
    {
        $this->heading($doc, $data, 'introduction');
        foreach (explode("\n\n", (string) ($data['sections']['introduction']['text'] ?? '')) as $para) {
            $doc->paragraph($para);
        }
    }

    private function writeFlowChart(DocxDocument $doc, array $data): void
    {
        $fc = $data['sections']['flow_chart'];
        $this->heading($doc, $data, 'flow_chart');

        $box = fn (array $item) => trim(($item['title'] ?? '')."\n".implode("\n", $item['lines'] ?? []));

        $rows = [
            [$box($fc['proponent'] ?? [])],
            ['↓'],
            [$box($fc['implementer'] ?? [])],
            ['↓'],
            [$box($fc['main_contractor'] ?? [])],
            ['↓'],
        ];
        $doc->table(null, $rows, ['align' => ['c']]);

        $doc->table(null, [[
            $box($fc['env_officer'] ?? []),
            $box($fc['env_consultant'] ?? []),
        ]], ['align' => ['c', 'c']]);

        $plans = $fc['plans'] ?? [];
        if ($plans !== []) {
            $doc->table(null, [$plans], ['align' => array_fill(0, count($plans), 'c')]);
        }
    }

    private function writePolicy(DocxDocument $doc, array $data): void
    {
        $this->heading($doc, $data, 'policy');
        $image = $data['policy_image'];

        if ($image['data_uri'] ?? null) {
            $doc->image(DocxDocument::binaryFromDataUri($image['data_uri']));
        } elseif ($image['pdf_path'] ?? null) {
            $doc->paragraph('Policy document attached as PDF.', ['italic' => true, 'color' => '808080']);
        } else {
            $doc->paragraph('No environmental policy document has been provided.', ['italic' => true, 'color' => '808080']);
        }
    }

    private function writeLocation(DocxDocument $doc, array $data): void
    {
        $this->heading($doc, $data, 'location');
        $image = $data['location_image'];

        if ($image['data_uri'] ?? null) {
            $doc->image(DocxDocument::binaryFromDataUri($image['data_uri']));
        } elseif ($image['pdf_path'] ?? null) {
            $doc->paragraph('Location map attached as PDF.', ['italic' => true, 'color' => '808080']);
        } else {
            $doc->paragraph('No location map has been provided.', ['italic' => true, 'color' => '808080']);
        }
    }

    private function writeParameters(DocxDocument $doc, array $data): void
    {
        $params = $data['sections']['parameters'];
        $this->heading($doc, $data, 'parameters');

        // Numbering comes from SECTION_SUBITEMS so the DOCX, the PDF and the table of contents
        // cannot drift apart — they did, and both sub-headings read "7.1".
        [$testParams, $monitoringPeriod] = EnvironmentReportViewData::SECTION_SUBITEMS['parameters'];

        $doc->heading("{$testParams['num']} {$testParams['title']}", 2);
        $groups = [
            'Water Quality' => $params['water'] ?? [],
            'Air Quality' => $params['air'] ?? [],
            'Noise Levels' => $params['noise'] ?? [],
            'Vibration Levels' => $params['vibration'] ?? [],
        ];
        $max = max(array_map('count', $groups) ?: [0]);
        $rows = [];
        for ($i = 0; $i < $max; $i++) {
            $row = [];
            foreach ($groups as $items) {
                $row[] = $items[$i] ?? '';
            }
            $rows[] = $row;
        }
        $doc->table(array_keys($groups), $rows);

        $doc->heading("{$monitoringPeriod['num']} {$monitoringPeriod['title']}", 2);
        $periodRows = [];
        foreach ($params['periods'] ?? [] as $period) {
            $periodRows[] = [$period['no'], $period['session'], $period['water'], $period['air'], $period['noise'], $period['vibration']];
        }
        $doc->table(['No.', 'Sampling Session', 'Water Quality', 'Air Quality', 'Noise Levels', 'Vibration Levels'], $periodRows);
    }

    private function writeResults(DocxDocument $doc, array $data): void
    {
        $results = $data['sections']['results'];
        $this->heading($doc, $data, 'results');

        $rows = [];
        foreach ($results['rows'] ?? [] as $row) {
            $rows[] = [
                $row['parameter'],
                $row['nwqs'] ?? '-',
                $row['doe'] ?? '-',
                $row['w1'] ?? '-',
                $row['w2'] ?? '-',
                $row['w3'] ?? '-',
                $row['w4'] ?? '-',
            ];
        }
        $doc->table(['Test Parameter', 'NWQS', 'DOE', 'W1', 'W2', 'W3', 'W4'], $rows);
    }

    private function writeBmp(DocxDocument $doc, array $data): void
    {
        $bmp = $data['sections']['bmp'];
        $this->heading($doc, $data, 'bmp');
        $doc->paragraph($bmp['intro'] ?? '');

        $rows = [];
        foreach ($bmp['items'] ?? [] as $item) {
            $rows[] = [$item['no'], $item['item'], ! empty($item['installed']) ? '✓' : ''];
        }
        $doc->table(['No.', 'Items', '(√)'], $rows);

        $photos = array_map(fn ($photo) => [
            'image' => DocxDocument::binaryFromDataUri($photo['data_uri']),
            'caption' => $photo['caption'],
        ], $data['bmp_photos'] ?? []);

        if ($photos !== []) {
            $doc->heading('Photographs', 2);
            $doc->imageGrid($photos, ['columns' => 2]);
        }
    }
}
