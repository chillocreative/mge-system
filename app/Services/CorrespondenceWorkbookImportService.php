<?php

namespace App\Services;

use App\Models\CorrespondenceDetail;
use App\Models\CorrespondenceImportSource;
use App\Models\CorrespondenceLink;
use App\Models\CorrespondencePartyReview;
use App\Models\Project;
use App\Models\ProjectCorrespondence;
use App\Models\ProjectParty;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class CorrespondenceWorkbookImportService
{
    /** Formula-only/report mirrors and lookup sheets are intentionally excluded. */
    public const SKIPPED_SHEETS = [
        'MAINBOARD', 'DASHBOARD', 'SUBCONTRACTORS', 'PROJECTS',
        'RFA (REPORT)', 'OUTGOING (REPORT)', 'RFWI (REPORT)', 'SITE LOG',
    ];

    /**
     * Detailed registers are read before their combined master registers. A
     * repeated reference is linked as a duplicate rather than inserted twice.
     */
    private const OPERATIONAL_SHEETS = [
        'OUTGOING (ADM)', 'INCOMING', 'MATERIALS APPROVAL)', 'METHOD OF STATEMENT',
        'DRAWING (DWG)', 'REPORT', 'RFI LOG', 'RFWI LOG', 'NCR LOG',
        'SITE MEMO (JPRIZ)', 'EI (JPRIZ)', 'SITE MEMO (MGE)', 'EI (MGE)',
        'PTW LOG', 'SUBCON', 'RFA LOG', 'MAIN ISSUES',
    ];

    public function preview(string $path, ?int $projectOverrideId = null, ?string $sourceName = null): array
    {
        $analysis = $this->analyse($path, $projectOverrideId, $sourceName);

        return $this->summary($analysis, false);
    }

    public function import(
        string $path,
        int $userId,
        ?int $projectOverrideId = null,
        ?string $sourceName = null,
    ): array {
        $analysis = $this->analyse($path, $projectOverrideId, $sourceName);
        $createdByKey = [];
        $counts = ['imported' => 0, 'duplicate' => 0, 'error' => 0, 'skipped' => 0];

        foreach ($analysis['records'] as $record) {
            $identity = $this->sourceIdentity($analysis['workbook_hash'], $record);
            $existingSource = CorrespondenceImportSource::where('source_identity', $identity)->first();
            if ($existingSource) {
                $counts[$existingSource->status] = ($counts[$existingSource->status] ?? 0) + 1;
                if ($existingSource->project_correspondence_id) {
                    $createdByKey[$record['key']] = $existingSource->project_correspondence_id;
                }

                continue;
            }

            if ($record['assessment'] === 'error') {
                $this->recordSource($analysis, $record, 'error', $userId, null, $record['error']);
                $counts['error']++;

                continue;
            }

            $existing = $this->findExisting($record);
            if (! $existing && isset($createdByKey[$record['key']])) {
                $existing = ProjectCorrespondence::find($createdByKey[$record['key']]);
            }
            if ($existing) {
                $this->recordSource($analysis, $record, 'duplicate', $userId, $existing->id);
                $createdByKey[$record['key']] = $existing->id;
                $counts['duplicate']++;

                continue;
            }

            try {
                $correspondence = DB::transaction(function () use ($analysis, $record, $userId) {
                    $correspondence = $this->createCorrespondence($record, $userId);
                    $this->recordSource($analysis, $record, 'imported', $userId, $correspondence->id);

                    return $correspondence;
                });
                $createdByKey[$record['key']] = $correspondence->id;
                $counts['imported']++;
            } catch (Throwable $e) {
                $this->recordSource($analysis, $record, 'error', $userId, null, $e->getMessage());
                $counts['error']++;
            }
        }

        $this->createLinks($analysis['records'], $createdByKey, $userId);

        return $this->summary($analysis, true) + ['result' => $counts];
    }

    private function analyse(string $path, ?int $projectOverrideId, ?string $sourceName): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new \InvalidArgumentException('The correspondence workbook cannot be read.');
        }

        $workbookHash = hash_file('sha256', $path);
        $spreadsheet = $this->loadSpreadsheet($path);
        $projectCatalog = $this->workbookProjects($spreadsheet->getSheetByName('PROJECTS'));
        $projects = Project::query()->get(['id', 'name', 'code']);
        $override = $projectOverrideId ? $projects->firstWhere('id', $projectOverrideId) : null;
        if ($projectOverrideId && ! $override) {
            throw new \InvalidArgumentException('The selected project does not exist.');
        }

        $records = [];
        $seen = [];
        $sheetSummaries = [];
        foreach (self::OPERATIONAL_SHEETS as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            if (! $sheet) {
                $sheetSummaries[] = ['sheet' => $sheetName, 'status' => 'missing', 'rows' => 0];

                continue;
            }

            $sheetCount = 0;
            for ($rowNumber = 6; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
                $record = $this->mapRow($sheetName, $sheet, $rowNumber);
                if (! $record) {
                    continue;
                }

                $sheetCount++;
                $project = $override ?: $this->matchProject($record['project_hint'], $projectCatalog, $projects);
                $record['project_id'] = $project?->id;
                $record['project_name'] = $project?->name;
                $record['key'] = $this->recordKey($record);
                $errors = $this->validateRecord($record);

                if (! $errors && isset($seen[$record['key']])) {
                    $record['assessment'] = 'duplicate';
                    $record['duplicate_source'] = $seen[$record['key']];
                } elseif (! $errors && $this->findExisting($record)) {
                    $record['assessment'] = 'duplicate';
                    $record['duplicate_source'] = 'database';
                } elseif ($errors) {
                    $record['assessment'] = 'error';
                    $record['error'] = implode(' ', $errors);
                } else {
                    $record['assessment'] = 'ready';
                    $seen[$record['key']] = $sheetName.'!'.$rowNumber;
                }

                $records[] = $record;
            }

            $sheetSummaries[] = ['sheet' => $sheetName, 'status' => 'read', 'rows' => $sheetCount];
        }

        $analysis = [
            'source_file' => $sourceName ?: basename($path),
            'workbook_hash' => $workbookHash,
            'records' => $records,
            'sheets' => $sheetSummaries,
            'skipped_sheets' => self::SKIPPED_SHEETS,
            'workbook_projects' => $projectCatalog,
        ];

        // Release cell collections before database duplicate checks/import work
        // continue. This is material for large source workbooks under cPanel's
        // 128 MB PHP memory limit.
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $analysis;
    }

    /**
     * Load only the operational registers and project lookup used by this
     * importer. The source workbook contains formula/report mirror sheets with
     * very large used ranges; loading those sheets can exhaust PHP memory even
     * though the importer intentionally ignores them.
     */
    private function loadSpreadsheet(string $path): Spreadsheet
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $reader = match ($extension) {
            'xlsx' => new Xlsx,
            'xls' => new Xls,
            default => throw new \InvalidArgumentException('The correspondence workbook must be an XLSX or XLS file.'),
        };

        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(array_values(array_unique([
            'PROJECTS',
            ...self::OPERATIONAL_SHEETS,
        ])));

        return $reader->load($path);
    }

    private function summary(array $analysis, bool $imported): array
    {
        $records = collect($analysis['records']);
        $counts = $records->countBy('assessment');

        return [
            'source_file' => $analysis['source_file'],
            'workbook_hash' => $analysis['workbook_hash'],
            'mode' => $imported ? 'import' : 'preview',
            'totals' => [
                'rows' => $records->count(),
                'ready' => (int) ($counts['ready'] ?? 0),
                'duplicate' => (int) ($counts['duplicate'] ?? 0),
                'error' => (int) ($counts['error'] ?? 0),
            ],
            'sheets' => $analysis['sheets'],
            'skipped_sheets' => $analysis['skipped_sheets'],
            'workbook_projects' => $analysis['workbook_projects'],
            'sample' => $records->take(50)->map(fn (array $row) => [
                'sheet' => $row['source_sheet'],
                'row' => $row['source_row'],
                'reference_no' => $row['reference_no'],
                'title' => $row['title'],
                'type' => $row['type'],
                'subtype' => $row['document_subtype'],
                'project' => $row['project_name'],
                'assessment' => $row['assessment'],
                'error' => $row['error'] ?? null,
                'warnings' => $row['warnings'],
            ])->values()->all(),
            'errors' => $records->where('assessment', 'error')->take(100)->map(fn (array $row) => [
                'sheet' => $row['source_sheet'],
                'row' => $row['source_row'],
                'reference_no' => $row['reference_no'],
                'message' => $row['error'],
            ])->values()->all(),
        ];
    }

    private function mapRow(string $sheetName, Worksheet $sheet, int $row): ?array
    {
        $v = fn (int $column) => $this->cell($sheet, $column, $row);
        $raw = [];
        for ($column = 1; $column <= min($sheet->getHighestDataColumn() === 'A' ? 1 : \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn()), 20); $column++) {
            $value = $v($column);
            if ($value !== null && $value !== '') {
                $raw[\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column)] = $this->jsonValue($value);
            }
        }

        $base = [
            'source_sheet' => $sheetName,
            'source_row' => $row,
            'type' => null,
            'document_subtype' => null,
            'reference_no' => null,
            'title' => null,
            'description' => null,
            'raised_date' => null,
            'due_date' => null,
            'actual_close_date' => null,
            'closing_reference' => null,
            'linked_reference' => null,
            'overall_status_raw' => null,
            'project_hint' => null,
            'from_name' => null,
            'to_name' => null,
            'details' => [],
            'reviews' => [],
            'warnings' => [],
            'raw_payload' => $raw,
        ];

        $record = match ($sheetName) {
            'OUTGOING (ADM)' => $base + [],
            default => $base,
        };

        switch ($sheetName) {
            case 'OUTGOING (ADM)':
                $record = array_replace($base, ['type' => 'outgoing', 'document_subtype' => 'ADM', 'reference_no' => $v(2), 'title' => $v(4), 'raised_date' => $v(5), 'actual_close_date' => $v(10) ?: $v(17), 'overall_status_raw' => $v(19), 'project_hint' => $v(18), 'from_name' => 'MGE', 'to_name' => $v(6), 'description' => $v(11), 'reviews' => [['role' => 'jpriz', 'status' => $v(7), 'closed_date' => $v(8) ?: $v(16)], ['role' => 'jps', 'status' => $v(9), 'closed_date' => $v(10) ?: $v(17)]]]);
                break;
            case 'INCOMING':
                $record = array_replace($base, ['type' => 'incoming', 'reference_no' => $v(9), 'title' => $v(2), 'raised_date' => $v(5), 'actual_close_date' => $v(11), 'overall_status_raw' => $v(10), 'project_hint' => $v(16), 'from_name' => $v(7), 'to_name' => $v(8), 'description' => $v(10), 'linked_reference' => $v(15), 'details' => ['category' => $v(3), 'document_reference' => $v(15), 'metadata' => ['received_date' => $v(6), 'file' => $v(4)]], 'reviews' => [['role' => 'jps', 'status' => $v(10), 'closed_date' => $v(11)]]]);
                break;
            case 'RFA LOG':
                $record = array_replace($base, ['type' => 'rfa', 'document_subtype' => $v(5), 'reference_no' => $v(2), 'title' => $v(4), 'raised_date' => $v(6), 'actual_close_date' => $v(11), 'overall_status_raw' => $v(15), 'project_hint' => $v(16), 'to_name' => $v(7), 'description' => $v(13), 'details' => ['category' => $v(5)], 'reviews' => [['role' => 'jpriz', 'status' => $v(8), 'closed_date' => $v(9)], ['role' => 'jps', 'status' => $v(10), 'closed_date' => $v(11)]]]);
                break;
            case 'RFI LOG':
                $record = array_replace($base, ['type' => 'rfi', 'reference_no' => $v(2), 'title' => $v(4), 'raised_date' => $v(5), 'actual_close_date' => $v(6), 'overall_status_raw' => $v(7), 'project_hint' => $v(10), 'description' => $v(8), 'details' => ['request_kind' => $v(9)], 'reviews' => [['role' => 'jpriz', 'status' => $v(7), 'closed_date' => $v(6)]]]);
                break;
            case 'RFWI LOG':
                $record = array_replace($base, ['type' => 'rfwi', 'reference_no' => $v(2), 'title' => $v(4), 'raised_date' => $v(8), 'actual_close_date' => $v(10), 'overall_status_raw' => $v(15) ?: $v(11), 'project_hint' => $v(14), 'description' => $v(12), 'details' => ['request_kind' => $v(5), 'work_category' => $v(6), 'inspection_type' => $v(7), 'inspection_date' => $v(9)], 'reviews' => [['role' => 'jpriz', 'status' => $v(11), 'closed_date' => $v(10)]]]);
                break;
            case 'NCR LOG':
                $record = array_replace($base, ['type' => 'ncr', 'reference_no' => $v(2), 'title' => $v(8) ?: $v(4), 'raised_date' => $v(12), 'actual_close_date' => $v(13), 'overall_status_raw' => $v(14), 'project_hint' => $v(16), 'to_name' => $v(3), 'description' => $v(15), 'details' => ['category' => $v(7), 'document_reference' => $v(6), 'work_scope' => $v(4), 'location' => $v(5), 'criticality' => $v(9), 'metadata' => ['issuer_name' => $v(10), 'issuer_designation' => $v(11), 'subcontractor_name' => $v(3)]], 'reviews' => [['role' => 'subcontractor', 'status' => $v(14), 'closed_date' => $v(13)]]]);
                break;
            case 'SITE MEMO (JPRIZ)':
                $record = array_replace($base, ['type' => 'site_memo', 'document_subtype' => 'JPRIZ', 'reference_no' => $v(2), 'title' => $v(3), 'raised_date' => $v(4), 'actual_close_date' => $v(5), 'closing_reference' => $v(6), 'overall_status_raw' => $v(7), 'project_hint' => $v(9), 'from_name' => 'JPRIZ', 'description' => $v(8), 'reviews' => [['role' => 'jpriz', 'status' => $v(7), 'closed_date' => $v(5)]]]);
                break;
            case 'EI (JPRIZ)':
                $record = array_replace($base, ['type' => 'ei', 'document_subtype' => 'JPRIZ', 'reference_no' => $v(2), 'title' => $v(4), 'raised_date' => $v(5), 'actual_close_date' => $v(6), 'closing_reference' => $v(7), 'overall_status_raw' => $v(8), 'project_hint' => $v(10), 'from_name' => 'JPRIZ', 'description' => $v(9), 'reviews' => [['role' => 'jpriz', 'status' => $v(8), 'closed_date' => $v(6)]]]);
                break;
            case 'SITE MEMO (MGE)':
                $record = array_replace($base, ['type' => 'site_memo', 'document_subtype' => 'MGE', 'reference_no' => $v(2), 'title' => $v(5), 'raised_date' => $v(3), 'actual_close_date' => $v(7), 'overall_status_raw' => $v(6), 'project_hint' => $v(10), 'from_name' => 'MGE', 'to_name' => $v(4), 'description' => $v(9), 'details' => ['action_required' => $v(8), 'memo_nature' => $v(11), 'metadata' => ['subcontractor_name' => $v(4)]], 'reviews' => [['role' => 'subcontractor', 'status' => $v(6), 'closed_date' => $v(7), 'remarks' => $v(8)]]]);
                break;
            case 'EI (MGE)':
                $record = array_replace($base, ['type' => 'ei', 'document_subtype' => 'MGE', 'reference_no' => $v(2), 'title' => $v(4), 'raised_date' => $v(5), 'due_date' => $v(6), 'actual_close_date' => $v(7), 'overall_status_raw' => $v(8), 'project_hint' => $v(10), 'from_name' => 'MGE', 'to_name' => $v(3), 'description' => $v(9), 'details' => ['compliance_due_date' => $v(6), 'complied_date' => $v(7), 'metadata' => ['subcontractor_name' => $v(3)]], 'reviews' => [['role' => 'subcontractor', 'status' => $v(8), 'closed_date' => $v(7)]]]);
                break;
            case 'PTW LOG':
                $record = array_replace($base, ['type' => 'ptw', 'reference_no' => $v(2), 'title' => $v(4), 'raised_date' => $v(5), 'due_date' => $v(6), 'actual_close_date' => $v(6), 'overall_status_raw' => $v(8), 'project_hint' => $v(10), 'to_name' => $v(11), 'description' => $v(9), 'details' => ['location' => $v(3), 'work_scope' => $v(4), 'metadata' => ['closed_by_name' => $v(7), 'contractor_name' => $v(11), 'risk_type' => $v(12)]], 'reviews' => [['role' => 'subcontractor', 'status' => $v(8), 'closed_date' => $v(6)]]]);
                break;
            case 'REPORT':
                $record = array_replace($base, ['type' => 'report', 'document_subtype' => $v(5), 'reference_no' => $v(2), 'title' => $v(3), 'raised_date' => $v(6), 'actual_close_date' => $v(11), 'overall_status_raw' => $v(10), 'project_hint' => $v(16), 'to_name' => $v(7), 'description' => $v(13), 'details' => ['category' => $v(4)], 'reviews' => [['role' => 'jpriz', 'status' => $v(8), 'closed_date' => $v(9)], ['role' => 'jps', 'status' => $v(10), 'closed_date' => $v(11)]]]);
                break;
            case 'MATERIALS APPROVAL)':
                $record = array_replace($base, ['type' => 'rfa', 'document_subtype' => 'MA', 'reference_no' => $v(2), 'title' => $v(3), 'raised_date' => $v(6), 'actual_close_date' => $v(12), 'overall_status_raw' => $v(11), 'project_hint' => $v(17), 'from_name' => $v(7), 'to_name' => $v(8), 'description' => $v(14), 'details' => ['category' => $v(4)], 'reviews' => [['role' => 'jpriz', 'status' => $v(9), 'closed_date' => $v(10)], ['role' => 'jps', 'status' => $v(11), 'closed_date' => $v(12)]]]);
                break;
            case 'METHOD OF STATEMENT':
                $record = array_replace($base, ['type' => 'rfa', 'document_subtype' => 'MOS', 'reference_no' => $v(2), 'title' => $v(3), 'raised_date' => $v(7), 'actual_close_date' => $v(12), 'overall_status_raw' => $v(11), 'project_hint' => $v(16), 'from_name' => $v(6), 'to_name' => $v(8), 'details' => ['category' => $v(4)], 'reviews' => [['role' => 'jpriz', 'status' => $v(9), 'closed_date' => $v(10)], ['role' => 'jps', 'status' => $v(11), 'closed_date' => $v(12)]]]);
                break;
            case 'DRAWING (DWG)':
                $record = array_replace($base, ['type' => 'drawing', 'document_subtype' => 'DWG', 'reference_no' => $v(2), 'title' => $v(3), 'raised_date' => $v(7), 'actual_close_date' => $v(12), 'overall_status_raw' => $v(11), 'project_hint' => $v(17), 'from_name' => $v(6), 'to_name' => $v(8), 'description' => $v(14), 'details' => ['category' => $v(4)], 'reviews' => [['role' => 'jpriz', 'status' => $v(9), 'closed_date' => $v(10)], ['role' => 'jps', 'status' => $v(11), 'closed_date' => $v(12)]]]);
                break;
            case 'SUBCON':
                $record = array_replace($base, ['type' => 'subcon', 'document_subtype' => $v(5), 'reference_no' => $v(2), 'title' => $v(3), 'raised_date' => $v(7), 'actual_close_date' => $v(10), 'overall_status_raw' => $v(9), 'project_hint' => $v(14), 'from_name' => $v(6), 'to_name' => $v(8), 'details' => ['category' => $v(4), 'metadata' => ['subcontractor_name' => $v(8)]], 'reviews' => [['role' => 'subcontractor', 'status' => $v(9), 'closed_date' => $v(10)]]]);
                break;
            case 'MAIN ISSUES':
                $record = array_replace($base, ['type' => 'outgoing', 'document_subtype' => $v(6) ?: 'ADM', 'reference_no' => $v(2), 'title' => $v(4), 'raised_date' => $v(5), 'actual_close_date' => $v(13) ?: $v(11), 'overall_status_raw' => $v(16), 'project_hint' => $v(15), 'from_name' => $v(7), 'to_name' => $v(8), 'description' => $v(14), 'reviews' => [['role' => 'client', 'status' => $v(9)], ['role' => 'jpriz', 'status' => $v(10), 'closed_date' => $v(11)], ['role' => 'jps', 'status' => $v(12), 'closed_date' => $v(13)]]]);
                break;
        }

        $record['reference_no'] = $this->text($record['reference_no']);
        $record['title'] = $this->text($record['title']);
        if ($record['reference_no'] === null && $record['title'] === null) {
            return null;
        }

        $record['document_subtype'] = $this->text($record['document_subtype']);
        $record['description'] = $this->text($record['description']);
        $record['overall_status_raw'] = $this->text($record['overall_status_raw']);
        $record['project_hint'] = $this->text($record['project_hint']);
        $record['from_name'] = $this->text($record['from_name']);
        $record['to_name'] = $this->text($record['to_name']);
        $record['closing_reference'] = $this->text($record['closing_reference']);
        $record['linked_reference'] = $this->text($record['linked_reference']);
        foreach (['raised_date', 'due_date', 'actual_close_date'] as $field) {
            [$record[$field], $warning] = $this->date($record[$field]);
            if ($warning) {
                $record['warnings'][] = ucfirst(str_replace('_', ' ', $field)).': '.$warning;
            }
        }
        foreach (['inspection_date', 'compliance_due_date', 'complied_date'] as $field) {
            if (array_key_exists($field, $record['details'])) {
                [$record['details'][$field], $warning] = $this->date($record['details'][$field]);
                if ($warning) {
                    $record['warnings'][] = ucfirst(str_replace('_', ' ', $field)).': '.$warning;
                }
            }
        }
        foreach ($record['reviews'] as &$review) {
            $review['status'] = $this->text($review['status'] ?? null);
            [$review['closed_date'], $warning] = $this->date($review['closed_date'] ?? null);
            if ($warning) {
                $record['warnings'][] = strtoupper($review['role']).' closed date: '.$warning;
            }
            $review['remarks'] = $this->text($review['remarks'] ?? null);
        }
        unset($review);

        if ($record['actual_close_date'] && $record['raised_date'] && $record['actual_close_date'] < $record['raised_date']) {
            $record['warnings'][] = 'Close date is earlier than opened date; source value was preserved.';
        }

        return $record;
    }

    private function createCorrespondence(array $record, int $userId): ProjectCorrespondence
    {
        $status = $this->overallStatus($record['overall_status_raw'], $record['reviews'], $record['actual_close_date']);
        $fromParty = $this->resolveParty((int) $record['project_id'], $record['from_name']);
        $toParty = $this->resolveParty((int) $record['project_id'], $record['to_name']);

        $correspondence = ProjectCorrespondence::create([
            'project_id' => $record['project_id'],
            'type' => $record['type'],
            'document_subtype' => $record['document_subtype'],
            'reference_no' => $record['reference_no'],
            'reference_no_is_manual' => true,
            'title' => $record['title'],
            'description' => $record['description'],
            'status' => $status,
            'other_status_text' => $status === 'others' ? $record['overall_status_raw'] : null,
            'raised_date' => $record['raised_date'],
            'due_date' => $record['due_date'],
            'actual_close_date' => $record['actual_close_date'],
            'closing_reference' => $record['closing_reference'],
            'from_party_id' => $fromParty?->id,
            'to_party_id' => $toParty?->id,
            'current_party_id' => $toParty?->id,
            'consultant_status' => $this->reviewStatus($record['reviews'], 'jpriz'),
            'client_status' => $this->reviewStatus($record['reviews'], 'jps') ?: $this->reviewStatus($record['reviews'], 'client'),
            'consultant_closed_date' => $this->reviewDate($record['reviews'], 'jpriz'),
            'client_closed_date' => $this->reviewDate($record['reviews'], 'jps') ?: $this->reviewDate($record['reviews'], 'client'),
            'created_by' => $userId,
        ]);

        $details = array_filter($record['details'], fn ($value) => $value !== null && $value !== '');
        $metadata = (array) ($details['metadata'] ?? []);
        $details['metadata'] = $metadata + [
            'source_sheet' => $record['source_sheet'],
            'source_row' => $record['source_row'],
            'from_name_raw' => $record['from_name'],
            'to_name_raw' => $record['to_name'],
            'linked_reference_raw' => $record['linked_reference'],
            'overall_status_raw' => $record['overall_status_raw'],
        ];
        CorrespondenceDetail::create(['project_correspondence_id' => $correspondence->id] + $details);

        foreach ($record['reviews'] as $sequence => $review) {
            if (! $review['status'] && ! $review['closed_date'] && ! $review['remarks']) {
                continue;
            }
            CorrespondencePartyReview::create([
                'project_correspondence_id' => $correspondence->id,
                'party_role' => $review['role'],
                'project_party_id' => $this->partyForRole((int) $record['project_id'], $review['role'])?->id,
                'status_raw' => $review['status'],
                'status_normalized' => $this->normalizedStatus($review['status']),
                'closed_date' => $review['closed_date'],
                'remarks' => $review['remarks'],
                'sequence' => $sequence,
            ]);
        }

        return $correspondence;
    }

    private function createLinks(array $records, array $createdByKey, int $userId): void
    {
        foreach ($records as $record) {
            $sourceId = $createdByKey[$record['key']] ?? $this->findExisting($record)?->id;
            if (! $sourceId || ! $record['project_id']) {
                continue;
            }
            foreach ([['reference' => $record['linked_reference'], 'type' => 'response_to'], ['reference' => $record['closing_reference'], 'type' => 'closes']] as $link) {
                if (! $link['reference']) {
                    continue;
                }
                $target = ProjectCorrespondence::query()
                    ->where('project_id', $record['project_id'])
                    ->whereRaw('LOWER(reference_no) = ?', [Str::lower(trim($link['reference']))])
                    ->first();
                if (! $target || $target->id === $sourceId) {
                    continue;
                }
                CorrespondenceLink::firstOrCreate([
                    'source_correspondence_id' => $sourceId,
                    'target_correspondence_id' => $target->id,
                    'relation_type' => $link['type'],
                ], ['created_by' => $userId]);
            }
        }
    }

    private function recordSource(array $analysis, array $record, string $status, int $userId, ?int $correspondenceId, ?string $error = null): CorrespondenceImportSource
    {
        return CorrespondenceImportSource::updateOrCreate(
            ['source_identity' => $this->sourceIdentity($analysis['workbook_hash'], $record)],
            [
                'workbook_hash' => $analysis['workbook_hash'],
                'source_file' => $analysis['source_file'],
                'source_sheet' => $record['source_sheet'],
                'source_row' => $record['source_row'],
                'source_reference' => $record['reference_no'],
                'status' => $status,
                'project_id' => $record['project_id'],
                'project_correspondence_id' => $correspondenceId,
                'raw_payload' => $record['raw_payload'],
                'warnings' => $record['warnings'],
                'error_message' => $error,
                'imported_by' => $userId,
            ],
        );
    }

    private function validateRecord(array $record): array
    {
        $errors = [];
        if (! $record['project_id']) {
            $errors[] = 'Project could not be matched; select a project override or correct the PROJECTS sheet.';
        }
        if (! $record['reference_no']) {
            $errors[] = 'Reference number is missing.';
        }
        if (! $record['title']) {
            $errors[] = 'Title is missing.';
        }
        if (! $record['raised_date']) {
            $errors[] = 'Opened/issued date is missing or invalid.';
        }

        return $errors;
    }

    private function findExisting(array $record): ?ProjectCorrespondence
    {
        if (! $record['project_id'] || ! $record['reference_no']) {
            return null;
        }

        return ProjectCorrespondence::withTrashed()
            ->where('project_id', $record['project_id'])
            ->whereRaw('LOWER(reference_no) = ?', [Str::lower(trim($record['reference_no']))])
            ->first();
    }

    private function matchProject(?string $hint, array $catalog, Collection $projects): ?Project
    {
        $values = array_values(array_filter([$hint]));
        if (! $hint && count($catalog) === 1) {
            $values = array_values(array_filter([$catalog[0]['contract_no'], $catalog[0]['name']]));
        }
        foreach ($catalog as $entry) {
            if ($hint && in_array($this->normalize($hint), [$this->normalize($entry['name']), $this->normalize($entry['contract_no'])], true)) {
                $values[] = $entry['contract_no'];
                $values[] = $entry['name'];
            }
        }

        foreach (array_unique($values) as $value) {
            $needle = $this->normalize($value);
            $exact = $projects->first(fn (Project $project) => in_array($needle, [$this->normalize($project->name), $this->normalize($project->code)], true));
            if ($exact) {
                return $exact;
            }
        }
        foreach (array_unique($values) as $value) {
            $needle = $this->normalize($value);
            if (strlen($needle) < 10) {
                continue;
            }
            $partial = $projects->first(function (Project $project) use ($needle) {
                $name = $this->normalize($project->name);
                $code = $this->normalize($project->code);

                return ($name && (str_contains($name, $needle) || str_contains($needle, $name)))
                    || ($code && (str_contains($code, $needle) || str_contains($needle, $code)));
            });
            if ($partial) {
                return $partial;
            }
        }

        return null;
    }

    private function workbookProjects(?Worksheet $sheet): array
    {
        if (! $sheet) {
            return [];
        }
        $projects = [];
        for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
            $name = $this->text($this->cell($sheet, 1, $row));
            $contract = $this->text($this->cell($sheet, 2, $row));
            if ($name || $contract) {
                $projects[] = ['name' => $name, 'contract_no' => $contract];
            }
        }

        return $projects;
    }

    private function resolveParty(int $projectId, ?string $name): ?ProjectParty
    {
        if (! $name || str_contains($name, "\n")) {
            return null;
        }
        $needle = $this->normalize($name);

        return ProjectParty::where('project_id', $projectId)->get()->first(function (ProjectParty $party) use ($needle) {
            $candidate = $this->normalize($party->name);

            return $candidate === $needle || (strlen($needle) >= 3 && (str_contains($candidate, $needle) || str_contains($needle, $candidate)));
        });
    }

    private function partyForRole(int $projectId, string $role): ?ProjectParty
    {
        $aliases = match ($role) {
            'jpriz' => ['jpriz', 'riz'],
            'jps', 'client' => ['jps', 'jabatan pengairan'],
            'subcontractor' => ['subcontractor', 'subkon'],
            default => [$role],
        };

        return ProjectParty::where('project_id', $projectId)->get()->first(function (ProjectParty $party) use ($aliases) {
            $name = $this->normalize($party->name.' '.$party->type);

            return collect($aliases)->contains(fn (string $alias) => str_contains($name, $this->normalize($alias)));
        });
    }

    private function overallStatus(?string $raw, array $reviews, ?string $closedDate): string
    {
        $value = $this->normalizedStatus($raw);
        if (in_array($value, ['declined', 'rejected'], true)) {
            return 'declined';
        }
        if ($closedDate || in_array($value, ['closed', 'received', 'approved', 'accepted', 'replied', 'resolved'], true)) {
            return 'closed';
        }
        if (in_array($value, ['pending', 'resubmit'], true)) {
            return 'pending';
        }
        foreach ($reviews as $review) {
            if (in_array($this->normalizedStatus($review['status'] ?? null), ['closed', 'received', 'approved', 'accepted', 'replied', 'resolved'], true)) {
                return 'closed';
            }
        }

        return $raw && ! in_array($value, [null, 'open'], true) ? 'others' : 'open';
    }

    private function normalizedStatus(?string $raw): ?string
    {
        $value = Str::upper(trim((string) $raw));
        if ($value === '' || $value === '-') {
            return null;
        }
        foreach ([
            'RESUBMIT' => 'resubmit', 'REJECT' => 'rejected', 'DECLIN' => 'declined',
            'APPROV' => 'approved', 'ACCEPT' => 'accepted', 'RECEIV' => 'received',
            'REPL' => 'replied', 'RESOLV' => 'resolved', 'CLOS' => 'closed',
            'DONE' => 'closed', 'PENDING' => 'pending', 'OPEN' => 'open',
        ] as $needle => $normalized) {
            if (str_contains($value, $needle)) {
                return $normalized;
            }
        }

        return 'other';
    }

    private function reviewStatus(array $reviews, string $role): ?string
    {
        foreach ($reviews as $review) {
            if ($review['role'] === $role) {
                return $review['status'];
            }
        }

        return null;
    }

    private function reviewDate(array $reviews, string $role): ?string
    {
        foreach ($reviews as $review) {
            if ($review['role'] === $role) {
                return $review['closed_date'];
            }
        }

        return null;
    }

    private function recordKey(array $record): string
    {
        return ($record['project_id'] ?: 'unmatched').'|'.$this->normalize($record['reference_no']);
    }

    private function sourceIdentity(string $workbookHash, array $record): string
    {
        return hash('sha256', $workbookHash.'|'.$record['source_sheet'].'|'.$record['source_row']);
    }

    private function cell(Worksheet $sheet, int $column, int $row): mixed
    {
        return $sheet->getCell([$column, $row])->getValue();
    }

    private function text(mixed $value): ?string
    {
        if ($value === null || is_bool($value)) {
            return null;
        }
        $text = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $text === '' || $text === '-' ? null : $text;
    }

    private function date(mixed $value): array
    {
        if ($value === null || $value === '' || trim((string) $value) === '-') {
            return [null, null];
        }
        try {
            if ($value instanceof DateTimeInterface) {
                return [Carbon::instance($value)->toDateString(), null];
            }
            if (is_numeric($value)) {
                return [Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString(), null];
            }
            $raw = trim((string) $value);
            foreach (['d/m/Y H:i', 'd/m/Y H:i:s', 'd/m/Y', 'd/m/y H:i', 'd/m/y', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
                try {
                    $date = Carbon::createFromFormat('!'.$format, $raw);
                    if ($date && $date->format($format) === $raw) {
                        return [$date->toDateString(), null];
                    }
                } catch (Throwable) {
                    // Try the next explicitly day-first format.
                }
            }

            return [null, "Unrecognised day-first date '{$raw}'."];
        } catch (Throwable) {
            return [null, 'Invalid date value.'];
        }
    }

    private function normalize(?string $value): string
    {
        return Str::lower(preg_replace('/[^a-z0-9]+/', '', Str::ascii((string) $value)));
    }

    private function jsonValue(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return is_scalar($value) || $value === null ? $value : (string) $value;
    }
}
