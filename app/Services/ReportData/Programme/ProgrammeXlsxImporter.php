<?php

namespace App\Services\ReportData\Programme;

use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProgrammeXlsxImporter
{
    /** Maximum number of physical rows scanned while building a preview. */
    private const SCAN_CAP = 2050;

    /** Maximum row_count reported by preview(). */
    private const ROW_COUNT_CAP = 2000;

    private const SUGGEST_KEYWORDS = [
        'name' => '/name|task|activity|description/i',
        'duration' => '/duration|dur/i',
        'start' => '/start/i',
        'finish' => '/finish|end/i',
        'actual_pct' => '/%\s*complete|percent\s*complete|actual|physical/i',
        'plan_pct' => '/plan|planned|scheduled/i',
        'outline_level' => '/outline\s*level|level|wbs\s*level/i',
    ];

    /**
     * @return array{
     *   headers: string[],
     *   sample: array<int, string[]>,
     *   row_count: int,
     *   suggested: array{name: ?int, duration: ?int, start: ?int, finish: ?int, actual_pct: ?int, plan_pct: ?int, outline_level: ?int}
     * }
     */
    public function preview(string $absolutePath): array
    {
        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);

        if (method_exists($reader, 'setReadFilter')) {
            $reader->setReadFilter($this->scanCapFilter());
        }

        $spreadsheet = $reader->load($absolutePath);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = $this->rowsAsStrings($sheet, self::SCAN_CAP);

        $headers = [];
        $headerIndex = null;
        foreach ($rows as $i => $row) {
            $nonEmpty = count(array_filter($row, fn ($v) => $v !== ''));
            if ($nonEmpty >= 2) {
                $headers = $row;
                $headerIndex = $i;
                break;
            }
        }

        $dataRows = $headerIndex === null ? [] : array_slice($rows, $headerIndex + 1);

        $rowCount = min(count($dataRows), self::ROW_COUNT_CAP);
        $sample = array_slice($dataRows, 0, 5);

        return [
            'headers' => $headers,
            'sample' => array_values($sample),
            'row_count' => $rowCount,
            'suggested' => $this->suggestMapping($headers),
        ];
    }

    /**
     * @param  array{name: int, duration: ?int, start: ?int, finish: ?int, actual_pct: ?int, plan_pct: ?int, outline_level: ?int}  $mapping
     * @return array<int, array{name: string, outline_level: int, duration_days: ?int, start: ?string, finish: ?string, actual_pct: ?float, plan_pct: ?float, is_summary: bool}>
     */
    public function import(string $absolutePath, array $mapping): array
    {
        $nameCol = $mapping['name'] ?? null;

        if ($nameCol === null) {
            throw ValidationException::withMessages([
                'file' => 'The work programme file has no name column selected.',
            ]);
        }

        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($absolutePath);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = $this->rowsAsStrings($sheet, null);

        $headerIndex = null;
        foreach ($rows as $i => $row) {
            $nonEmpty = count(array_filter($row, fn ($v) => $v !== ''));
            if ($nonEmpty >= 2) {
                $headerIndex = $i;
                break;
            }
        }

        $headers = $headerIndex === null ? [] : $rows[$headerIndex];
        $dataRows = $headerIndex === null ? [] : array_slice($rows, $headerIndex + 1);

        $rawNames = [];
        foreach ($dataRows as $row) {
            $rawNames[] = $row[$nameCol] ?? '';
        }
        $indentUnit = ActivityNormaliser::inferIndentUnit($rawNames);

        $fractionMode = [
            'actual_pct' => $this->detectFractionMode($dataRows, $mapping['actual_pct'] ?? null, $headers),
            'plan_pct' => $this->detectFractionMode($dataRows, $mapping['plan_pct'] ?? null, $headers),
        ];

        $activities = [];

        foreach ($dataRows as $row) {
            $rawName = $row[$nameCol] ?? '';
            if (trim($rawName) === '') {
                continue;
            }

            $outlineCol = $mapping['outline_level'] ?? null;
            $outlineRaw = $outlineCol !== null ? ($row[$outlineCol] ?? '') : '';

            if ($outlineCol !== null && is_numeric($outlineRaw)) {
                $level = max(1, (int) round((float) $outlineRaw));
                $name = trim($rawName);
            } else {
                $derived = ActivityNormaliser::outlineFromIndent($rawName, $indentUnit);
                $level = $derived['level'];
                $name = $derived['name'];
            }

            $durationCol = $mapping['duration'] ?? null;
            $startCol = $mapping['start'] ?? null;
            $finishCol = $mapping['finish'] ?? null;
            $actualCol = $mapping['actual_pct'] ?? null;
            $planCol = $mapping['plan_pct'] ?? null;

            $activities[] = [
                'name' => $name,
                'outline_level' => $level,
                'duration_days' => $durationCol !== null ? ActivityNormaliser::durationDays($row[$durationCol] ?? null) : null,
                'start' => $startCol !== null ? ActivityNormaliser::date($row[$startCol] ?? null) : null,
                'finish' => $finishCol !== null ? ActivityNormaliser::date($row[$finishCol] ?? null) : null,
                'actual_pct' => $actualCol !== null ? ActivityNormaliser::percent($row[$actualCol] ?? null, $fractionMode['actual_pct']) : null,
                'plan_pct' => $planCol !== null ? ActivityNormaliser::percent($row[$planCol] ?? null, $fractionMode['plan_pct']) : null,
                'is_summary' => false,
            ];
        }

        if ($activities === []) {
            throw ValidationException::withMessages([
                'file' => 'No activities with a name were found using the selected column mapping.',
            ]);
        }

        return ActivityNormaliser::deriveSummaries($activities);
    }

    /**
     * @return array{name: ?int, duration: ?int, start: ?int, finish: ?int, actual_pct: ?int, plan_pct: ?int, outline_level: ?int}
     */
    private function suggestMapping(array $headers): array
    {
        $suggested = [
            'name' => null,
            'duration' => null,
            'start' => null,
            'finish' => null,
            'actual_pct' => null,
            'plan_pct' => null,
            'outline_level' => null,
        ];

        foreach ($headers as $index => $header) {
            foreach (self::SUGGEST_KEYWORDS as $key => $pattern) {
                if ($suggested[$key] === null && preg_match($pattern, (string) $header)) {
                    $suggested[$key] = $index;
                }
            }
        }

        return $suggested;
    }

    private function detectFractionMode(array $dataRows, ?int $col, array $headers): bool
    {
        if ($col === null) {
            return false;
        }

        $header = (string) ($headers[$col] ?? '');
        if (str_contains($header, '%')) {
            return false;
        }

        $sawNumeric = false;
        foreach ($dataRows as $row) {
            $raw = $row[$col] ?? '';
            if ($raw === '') {
                continue;
            }

            $value = str_replace(['%', ','], ['', '.'], (string) $raw);
            if (! is_numeric($value)) {
                continue;
            }

            $sawNumeric = true;
            if ((float) $value > 1) {
                return false;
            }
        }

        return $sawNumeric;
    }

    /**
     * @return string[][]
     */
    private function rowsAsStrings(Worksheet $sheet, ?int $capRows): array
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        $limit = $capRows !== null ? min($highestRow, $capRows) : $highestRow;

        $rows = [];
        for ($r = 1; $r <= $limit; $r++) {
            $row = [];
            for ($c = 1; $c <= $highestColumnIndex; $c++) {
                $cell = $sheet->getCellByColumnAndRow($c, $r);
                $value = $cell?->getValue();
                $row[] = $value === null ? '' : (string) $value;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function scanCapFilter(): IReadFilter
    {
        return new class(self::SCAN_CAP) implements IReadFilter
        {
            public function __construct(private int $capRow) {}

            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return $row <= $this->capRow;
            }
        };
    }
}
