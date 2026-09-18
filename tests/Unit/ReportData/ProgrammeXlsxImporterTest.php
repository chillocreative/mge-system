<?php

namespace Tests\Unit\ReportData;

use App\Services\ReportData\Programme\ProgrammeXlsxImporter;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProgrammeXlsxImporterTest extends TestCase
{
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->tempFiles = [];

        parent::tearDown();
    }

    private function tempPath(string $extension): string
    {
        $path = sys_get_temp_dir().'/programme-xlsx-test-'.uniqid().'.'.$extension;
        $this->tempFiles[] = $path;

        return $path;
    }

    private function writeXlsx(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $r => $row) {
            foreach ($row as $c => $value) {
                $sheet->setCellValueByColumnAndRow($c + 1, $r + 1, $value);
            }
        }

        $path = $this->tempPath('xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function writeCsv(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $r => $row) {
            foreach ($row as $c => $value) {
                $sheet->setCellValueByColumnAndRow($c + 1, $r + 1, $value);
            }
        }

        $path = $this->tempPath('csv');
        (new Csv($spreadsheet))->save($path);

        return $path;
    }

    private function sampleRows(): array
    {
        return [
            ['Activity Name', 'Duration', 'Start', 'Finish', '% Complete', 'Planned %'],
            ['Mobilization', '5 days', '01/01/2026', '08/01/2026', '100', '100'],
            ['  Excavation', '10 days', '09/01/2026', '20/01/2026', '80', '90'],
            ['  Backfilling', '5 days', '21/01/2026', '27/01/2026', '40', '60'],
            ['Foundation', '20 days', '28/01/2026', '20/02/2026', '30', '50'],
        ];
    }

    public function test_preview_detects_headers_and_suggested_mapping(): void
    {
        $path = $this->writeXlsx($this->sampleRows());
        $importer = new ProgrammeXlsxImporter;

        $preview = $importer->preview($path);

        $this->assertSame(
            ['Activity Name', 'Duration', 'Start', 'Finish', '% Complete', 'Planned %'],
            $preview['headers']
        );
        $this->assertSame(4, $preview['row_count']);
        $this->assertCount(4, $preview['sample']);
        $this->assertSame('Mobilization', $preview['sample'][0][0]);

        $this->assertSame(0, $preview['suggested']['name']);
        $this->assertSame(1, $preview['suggested']['duration']);
        $this->assertSame(2, $preview['suggested']['start']);
        $this->assertSame(3, $preview['suggested']['finish']);
        $this->assertSame(4, $preview['suggested']['actual_pct']);
        $this->assertSame(5, $preview['suggested']['plan_pct']);
        $this->assertNull($preview['suggested']['outline_level']);
    }

    public function test_suggestions_do_not_let_actual_start_claim_the_percent_column(): void
    {
        $path = $this->writeXlsx([
            ['Task Name', 'Actual Start', 'Actual Finish', '% Complete', 'Duration', 'Outline Level'],
            ['Piling', '01/01/2026', '10/01/2026', '45%', '10 days', 1],
        ]);

        $suggested = (new ProgrammeXlsxImporter)->preview($path)['suggested'];

        $this->assertSame(0, $suggested['name']);
        $this->assertSame(1, $suggested['start']);
        $this->assertSame(2, $suggested['finish']);
        $this->assertSame(3, $suggested['actual_pct']);
        $this->assertSame(4, $suggested['duration']);
        $this->assertSame(5, $suggested['outline_level']);
        $this->assertNull($suggested['plan_pct']);
    }

    public function test_preview_supports_csv(): void
    {
        $path = $this->writeCsv($this->sampleRows());
        $importer = new ProgrammeXlsxImporter;

        $preview = $importer->preview($path);

        $this->assertSame('Activity Name', $preview['headers'][0]);
        $this->assertSame(4, $preview['row_count']);
    }

    public function test_preview_row_count_is_capped(): void
    {
        $rows = [['Activity Name', 'Duration']];
        for ($i = 0; $i < 2100; $i++) {
            $rows[] = ["Task $i", '1'];
        }

        $path = $this->writeXlsx($rows);
        $importer = new ProgrammeXlsxImporter;

        $preview = $importer->preview($path);

        $this->assertSame(2000, $preview['row_count']);
    }

    public function test_import_maps_columns_and_derives_summaries(): void
    {
        $path = $this->writeXlsx($this->sampleRows());
        $importer = new ProgrammeXlsxImporter;

        $activities = $importer->import($path, [
            'name' => 0,
            'duration' => 1,
            'start' => 2,
            'finish' => 3,
            'actual_pct' => 4,
            'plan_pct' => 5,
            'outline_level' => null,
        ]);

        $this->assertCount(4, $activities);

        $this->assertSame('Mobilization', $activities[0]['name']);
        $this->assertSame(1, $activities[0]['outline_level']);
        $this->assertSame(5, $activities[0]['duration_days']);
        $this->assertSame('2026-01-01', $activities[0]['start']);
        $this->assertSame('2026-01-08', $activities[0]['finish']);
        $this->assertSame(100.0, $activities[0]['actual_pct']);
        $this->assertTrue($activities[0]['is_summary']);

        $this->assertSame('Excavation', $activities[1]['name']);
        $this->assertSame(2, $activities[1]['outline_level']);

        $this->assertSame('Foundation', $activities[3]['name']);
        $this->assertSame(1, $activities[3]['outline_level']);
    }

    public function test_import_skips_rows_with_empty_name(): void
    {
        $rows = [
            ['Activity Name', 'Duration'],
            ['Mobilization', '5'],
            ['', '3'],
            ['Foundation', '20'],
        ];
        $path = $this->writeXlsx($rows);
        $importer = new ProgrammeXlsxImporter;

        $activities = $importer->import($path, ['name' => 0, 'duration' => 1]);

        $this->assertCount(2, $activities);
        $this->assertSame('Mobilization', $activities[0]['name']);
        $this->assertSame('Foundation', $activities[1]['name']);
    }

    public function test_import_uses_mapped_outline_level_when_numeric(): void
    {
        $rows = [
            ['Activity Name', 'Level'],
            ['Mobilization', '1'],
            ['Excavation', '2'],
        ];
        $path = $this->writeXlsx($rows);
        $importer = new ProgrammeXlsxImporter;

        $activities = $importer->import($path, ['name' => 0, 'outline_level' => 1]);

        $this->assertSame(1, $activities[0]['outline_level']);
        $this->assertSame(2, $activities[1]['outline_level']);
    }

    public function test_import_auto_detects_fraction_mode_for_percent_columns(): void
    {
        $rows = [
            ['Activity Name', 'Progress'],
            ['Mobilization', '0.5'],
            ['Excavation', '0.25'],
        ];
        $path = $this->writeXlsx($rows);
        $importer = new ProgrammeXlsxImporter;

        $activities = $importer->import($path, ['name' => 0, 'actual_pct' => 1]);

        $this->assertSame(50.0, $activities[0]['actual_pct']);
        $this->assertSame(25.0, $activities[1]['actual_pct']);
    }

    public function test_import_throws_when_name_mapping_missing(): void
    {
        $path = $this->writeXlsx($this->sampleRows());
        $importer = new ProgrammeXlsxImporter;

        $this->expectException(ValidationException::class);

        $importer->import($path, ['name' => null]);
    }

    public function test_import_throws_when_all_names_empty(): void
    {
        $rows = [
            ['Activity Name', 'Duration'],
            ['', '5'],
            ['', '3'],
        ];
        $path = $this->writeXlsx($rows);
        $importer = new ProgrammeXlsxImporter;

        $this->expectException(ValidationException::class);

        $importer->import($path, ['name' => 0]);
    }

    public function test_import_throws_validation_exception_for_unreadable_garbage_bytes(): void
    {
        $path = $this->tempPath('xlsx');
        file_put_contents($path, random_bytes(256));

        $importer = new ProgrammeXlsxImporter;

        $this->expectException(ValidationException::class);

        $importer->import($path, ['name' => 0]);
    }

    public function test_preview_throws_validation_exception_for_unreadable_garbage_bytes(): void
    {
        $path = $this->tempPath('xlsx');
        file_put_contents($path, random_bytes(256));

        $importer = new ProgrammeXlsxImporter;

        $this->expectException(ValidationException::class);

        $importer->preview($path);
    }

    public function test_import_throws_validation_exception_for_unsupported_extension(): void
    {
        $path = $this->tempPath('txt');
        file_put_contents($path, 'Activity Name,Duration'.PHP_EOL.'Mobilization,5'.PHP_EOL);

        $importer = new ProgrammeXlsxImporter;

        $this->expectException(ValidationException::class);

        $importer->import($path, ['name' => 0]);
    }

    public function test_import_throws_when_more_than_2000_activities(): void
    {
        $rows = [['Activity Name', 'Duration']];
        for ($i = 0; $i < 2100; $i++) {
            $rows[] = ["Task {$i}", '1'];
        }

        $path = $this->writeXlsx($rows);
        $importer = new ProgrammeXlsxImporter;

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/more than 2,000 activities/');

        $importer->import($path, ['name' => 0]);
    }

    public function test_import_clamps_long_names_and_outline_levels_and_negative_durations(): void
    {
        $longName = str_repeat('A', 300);
        $rows = [
            ['Activity Name', 'Duration', 'Level'],
            [$longName, '-5', '999'],
        ];
        $path = $this->writeXlsx($rows);
        $importer = new ProgrammeXlsxImporter;

        $activities = $importer->import($path, ['name' => 0, 'duration' => 1, 'outline_level' => 2]);

        $this->assertSame(255, strlen($activities[0]['name']));
        $this->assertSame(str_repeat('A', 255), $activities[0]['name']);
        $this->assertSame(255, $activities[0]['outline_level']);
        $this->assertNull($activities[0]['duration_days']);
    }

    public function test_fraction_mode_is_detected_even_when_header_contains_percent_sign(): void
    {
        // Excel percent-formatted cells store the underlying fraction (0.45), not "45%"; the
        // header containing '%' must not short-circuit fraction detection.
        $rows = [
            ['Activity Name', '% Complete'],
            ['Mobilization', '0.45'],
            ['Excavation', '0.9'],
        ];
        $path = $this->writeXlsx($rows);
        $importer = new ProgrammeXlsxImporter;

        $activities = $importer->import($path, ['name' => 0, 'actual_pct' => 1]);

        $this->assertSame(45.0, $activities[0]['actual_pct']);
        $this->assertSame(90.0, $activities[1]['actual_pct']);
    }

    public function test_fraction_mode_is_not_used_when_raw_values_carry_a_percent_sign(): void
    {
        $rows = [
            ['Activity Name', 'Progress'],
            ['Mobilization', '45%'],
            ['Excavation', '0.9%'],
        ];
        $path = $this->writeXlsx($rows);
        $importer = new ProgrammeXlsxImporter;

        $activities = $importer->import($path, ['name' => 0, 'actual_pct' => 1]);

        $this->assertSame(45.0, $activities[0]['actual_pct']);
        $this->assertSame(0.9, $activities[1]['actual_pct']);
    }

    public function test_fraction_mode_all_zeros_is_not_treated_as_fraction_mode(): void
    {
        $rows = [
            ['Activity Name', 'Progress'],
            ['Mobilization', '0'],
            ['Excavation', '0'],
        ];
        $path = $this->writeXlsx($rows);
        $importer = new ProgrammeXlsxImporter;

        $activities = $importer->import($path, ['name' => 0, 'actual_pct' => 1]);

        $this->assertSame(0.0, $activities[0]['actual_pct']);
        $this->assertSame(0.0, $activities[1]['actual_pct']);
    }
}
