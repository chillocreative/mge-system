<?php

namespace Tests\Unit\ReportData;

use App\Services\ReportData\Programme\ProgrammeMspdiImporter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProgrammeMspdiImporterTest extends TestCase
{
    private function fixturePath(): string
    {
        return __DIR__.'/../../fixtures/programme/sample-mspdi.xml';
    }

    public function test_import_parses_fixture_and_skips_project_summary(): void
    {
        $importer = new ProgrammeMspdiImporter;

        $activities = $importer->import($this->fixturePath());

        // 8 <Task> elements in the fixture, UID 0 (project summary) skipped.
        $this->assertCount(7, $activities);
        $this->assertSame('Mobilization', $activities[0]['name']);
    }

    public function test_import_maps_outline_levels_1_through_3(): void
    {
        $importer = new ProgrammeMspdiImporter;

        $activities = $importer->import($this->fixturePath());

        $levels = array_column($activities, 'outline_level');
        $this->assertContains(1, $levels);
        $this->assertContains(2, $levels);
        $this->assertContains(3, $levels);
    }

    public function test_import_parses_duration_from_iso8601(): void
    {
        $importer = new ProgrammeMspdiImporter;

        $activities = $importer->import($this->fixturePath());

        $mobilization = collect($activities)->firstWhere('name', 'Mobilization');
        $this->assertSame(5, $mobilization['duration_days']);
    }

    public function test_import_maps_percent_complete_to_actual_pct_and_leaves_plan_pct_null(): void
    {
        $importer = new ProgrammeMspdiImporter;

        $activities = $importer->import($this->fixturePath());

        $mobilization = collect($activities)->firstWhere('name', 'Mobilization');
        $this->assertSame(100.0, $mobilization['actual_pct']);
        $this->assertNull($mobilization['plan_pct']);
    }

    public function test_import_maps_dates(): void
    {
        $importer = new ProgrammeMspdiImporter;

        $activities = $importer->import($this->fixturePath());

        $mobilization = collect($activities)->firstWhere('name', 'Mobilization');
        $this->assertSame('2026-01-01', $mobilization['start']);
        $this->assertSame('2026-01-08', $mobilization['finish']);
    }

    public function test_import_has_at_least_one_summary_row_from_source_flag(): void
    {
        $importer = new ProgrammeMspdiImporter;

        $activities = $importer->import($this->fixturePath());

        // Site Establishment has Summary=1 in the fixture and is followed by
        // a deeper outline level, so deriveSummaries confirms it too.
        $siteEstablishment = collect($activities)->firstWhere('name', 'Site Establishment');
        $this->assertTrue($siteEstablishment['is_summary']);
    }

    public function test_import_derives_summaries_from_outline_structure(): void
    {
        $importer = new ProgrammeMspdiImporter;

        $activities = $importer->import($this->fixturePath());

        // Substructure Works has Summary=0 in the XML but is followed by a
        // deeper "Excavation" row, so deriveSummaries marks it a summary too.
        $substructure = collect($activities)->firstWhere('name', 'Substructure Works');
        $this->assertTrue($substructure['is_summary']);

        $superstructure = collect($activities)->firstWhere('name', 'Superstructure Works');
        $this->assertFalse($superstructure['is_summary']);
    }

    public function test_import_throws_validation_exception_for_invalid_xml(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'invalid-mspdi').'.xml';
        file_put_contents($path, 'not xml at all <<<');

        $importer = new ProgrammeMspdiImporter;

        try {
            $this->expectException(ValidationException::class);
            $importer->import($path);
        } finally {
            unlink($path);
        }
    }
}
