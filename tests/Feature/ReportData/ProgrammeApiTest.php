<?php

namespace Tests\Feature\ReportData;

use App\Models\MonthlyReport;
use App\Models\Project;
use App\Models\ProjectProgrammeVersion;
use App\Models\ProjectProgressPeriod;
use App\Models\User;
use App\Services\MonthlyReport\Export\PdfExporter;
use App\Services\MonthlyReport\MonthlyReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProgrammeApiTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private User $viewer;

    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->editor = User::create(['first_name' => 'Ed', 'last_name' => 'Itor', 'email' => 'ed-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $this->editor->givePermissionTo(['projects.view', 'projects.edit']);

        $this->viewer = User::create(['first_name' => 'Vi', 'last_name' => 'Ewer', 'email' => 'vi-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $this->viewer->givePermissionTo(['projects.view']);
    }

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

    private function project(): Project
    {
        return Project::create(['name' => 'P', 'code' => 'P'.random_int(100000, 999999), 'status' => 'in_progress']);
    }

    private function sampleRows(): array
    {
        return [
            ['Activity Name', 'Duration', 'Start', 'Finish', '% Complete', 'Planned %'],
            ['Mobilization', '5 days', '01/01/2026', '08/01/2026', '100', '100'],
            ['Excavation', '10 days', '09/01/2026', '20/01/2026', '80', '90'],
            ['Foundation', '20 days', '21/01/2026', '20/02/2026', '30', '50'],
        ];
    }

    private function xlsxUploadedFile(string $name = 'programme.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($this->sampleRows() as $r => $row) {
            foreach ($row as $c => $value) {
                $sheet->setCellValueByColumnAndRow($c + 1, $r + 1, $value);
            }
        }

        $path = sys_get_temp_dir().'/programme-api-test-'.uniqid().'.xlsx';
        $this->tempFiles[] = $path;
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function mspdiUploadedFile(): UploadedFile
    {
        return new UploadedFile(base_path('tests/fixtures/programme/sample-mspdi.xml'), 'sample-mspdi.xml', 'text/xml', null, true);
    }

    private function previewAndGetToken(int $projectId): array
    {
        $res = $this->actingAs($this->editor)
            ->post("/api/projects/{$projectId}/programme-versions/preview", [
                'file' => $this->xlsxUploadedFile(),
            ])
            ->assertOk();

        return $res->json('data');
    }

    public function test_preview_returns_headers_sample_and_suggested_mapping(): void
    {
        $project = $this->project();

        $data = $this->previewAndGetToken($project->id);

        $this->assertSame(
            ['Activity Name', 'Duration', 'Start', 'Finish', '% Complete', 'Planned %'],
            $data['headers']
        );
        $this->assertSame(3, $data['row_count']);
        $this->assertSame(0, $data['suggested']['name']);
        $this->assertNotEmpty($data['token']);
        $this->assertSame('programme.xlsx', $data['file_name']);
    }

    public function test_import_with_mapping_creates_version_and_activities_and_marks_current(): void
    {
        $project = $this->project();
        $preview = $this->previewAndGetToken($project->id);

        $res = $this->actingAs($this->editor)
            ->postJson("/api/projects/{$project->id}/programme-versions/import", [
                'token' => $preview['token'],
                'mapping' => [
                    'name' => $preview['suggested']['name'],
                    'duration' => $preview['suggested']['duration'],
                    'start' => $preview['suggested']['start'],
                    'finish' => $preview['suggested']['finish'],
                    'actual_pct' => $preview['suggested']['actual_pct'],
                    'plan_pct' => $preview['suggested']['plan_pct'],
                ],
                'label' => 'Baseline Programme',
                'status_date' => '2026-01-31',
            ])
            ->assertCreated();

        $versionId = $res->json('data.id');
        $version = ProjectProgrammeVersion::findOrFail($versionId);

        $this->assertTrue($version->is_current);
        $this->assertSame(3, $version->activity_count);
        $this->assertSame(3, $version->activities()->count());
        $this->assertSame('xlsx', $version->source_type);
        Storage::disk('local')->assertExists($version->source_file_path);

        $list = $this->actingAs($this->editor)->getJson("/api/projects/{$project->id}/programme-versions")->assertOk();
        $this->assertCount(1, $list->json('data'));
        $this->assertSame('Ed Itor', $list->json('data.0.imported_by_name'));
        $this->assertTrue($list->json('data.0.is_current'));
    }

    public function test_import_with_token_from_another_project_is_rejected(): void
    {
        $projectA = $this->project();
        $projectB = $this->project();

        $preview = $this->previewAndGetToken($projectA->id);

        $this->actingAs($this->editor)
            ->postJson("/api/projects/{$projectB->id}/programme-versions/import", [
                'token' => $preview['token'],
                'mapping' => ['name' => $preview['suggested']['name']],
                'label' => 'Cross project',
            ])
            ->assertStatus(422);
    }

    public function test_import_mspdi_creates_version(): void
    {
        $project = $this->project();

        $res = $this->actingAs($this->editor)
            ->post("/api/projects/{$project->id}/programme-versions/import-mspdi", [
                'file' => $this->mspdiUploadedFile(),
                'label' => 'MSPDI Import',
            ])
            ->assertCreated();

        $version = ProjectProgrammeVersion::findOrFail($res->json('data.id'));
        $this->assertSame('mspdi', $version->source_type);
        $this->assertTrue($version->is_current);
        $this->assertGreaterThan(0, $version->activity_count);
        Storage::disk('local')->assertExists($version->source_file_path);
    }

    public function test_version_of_another_project_returns_404(): void
    {
        $projectA = $this->project();
        $projectB = $this->project();

        $res = $this->actingAs($this->editor)
            ->post("/api/projects/{$projectA->id}/programme-versions/import-mspdi", [
                'file' => $this->mspdiUploadedFile(),
                'label' => 'MSPDI Import',
            ])
            ->assertCreated();
        $versionId = $res->json('data.id');

        $this->actingAs($this->editor)
            ->putJson("/api/projects/{$projectB->id}/programme-versions/{$versionId}", ['label' => 'New'])
            ->assertStatus(404);

        $this->actingAs($this->editor)
            ->getJson("/api/projects/{$projectB->id}/programme-versions/{$versionId}/activities")
            ->assertStatus(404);

        $this->actingAs($this->editor)
            ->deleteJson("/api/projects/{$projectB->id}/programme-versions/{$versionId}")
            ->assertStatus(404);
    }

    public function test_delete_current_version_promotes_next_latest(): void
    {
        $project = $this->project();

        $v1 = $this->actingAs($this->editor)->post("/api/projects/{$project->id}/programme-versions/import-mspdi", [
            'file' => $this->mspdiUploadedFile(), 'label' => 'V1', 'status_date' => '2026-01-15',
        ])->assertCreated()->json('data.id');

        $v2 = $this->actingAs($this->editor)->post("/api/projects/{$project->id}/programme-versions/import-mspdi", [
            'file' => $this->mspdiUploadedFile(), 'label' => 'V2', 'status_date' => '2026-02-15',
        ])->assertCreated()->json('data.id');

        $this->assertTrue(ProjectProgrammeVersion::findOrFail($v2)->is_current);

        $this->actingAs($this->editor)
            ->deleteJson("/api/projects/{$project->id}/programme-versions/{$v2}")
            ->assertOk();

        $this->assertNull(ProjectProgrammeVersion::find($v2));
        $this->assertTrue(ProjectProgrammeVersion::findOrFail($v1)->is_current);
    }

    public function test_view_only_user_is_forbidden_on_write_actions(): void
    {
        $project = $this->project();

        $this->actingAs($this->viewer)
            ->post("/api/projects/{$project->id}/programme-versions/preview", [
                'file' => $this->xlsxUploadedFile(),
            ])
            ->assertStatus(403);

        $this->actingAs($this->viewer)
            ->post("/api/projects/{$project->id}/programme-versions/import-mspdi", [
                'file' => $this->mspdiUploadedFile(), 'label' => 'V1',
            ])
            ->assertStatus(403);

        $version = $this->actingAs($this->editor)->post("/api/projects/{$project->id}/programme-versions/import-mspdi", [
            'file' => $this->mspdiUploadedFile(), 'label' => 'V1',
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->viewer)
            ->putJson("/api/projects/{$project->id}/programme-versions/{$version}", ['label' => 'New'])
            ->assertStatus(403);

        $this->actingAs($this->viewer)
            ->deleteJson("/api/projects/{$project->id}/programme-versions/{$version}")
            ->assertStatus(403);

        // reading remains allowed for view-only users
        $this->actingAs($this->viewer)
            ->getJson("/api/projects/{$project->id}/programme-versions")
            ->assertOk();
    }

    public function test_activities_endpoint_paginates(): void
    {
        $project = $this->project();

        $version = $this->actingAs($this->editor)->post("/api/projects/{$project->id}/programme-versions/import-mspdi", [
            'file' => $this->mspdiUploadedFile(), 'label' => 'V1',
        ])->assertCreated()->json('data.id');

        $res = $this->actingAs($this->editor)
            ->getJson("/api/projects/{$project->id}/programme-versions/{$version}/activities?per_page=2")
            ->assertOk();

        $this->assertCount(2, $res->json('data.data'));
        $this->assertSame(2, $res->json('data.per_page'));
        $this->assertArrayHasKey('seq', $res->json('data.data.0'));

        $resCapped = $this->actingAs($this->editor)
            ->getJson("/api/projects/{$project->id}/programme-versions/{$version}/activities?per_page=9999")
            ->assertOk();
        $this->assertSame(500, $resCapped->json('data.per_page'));
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $project = $this->project();

        $this->getJson("/api/projects/{$project->id}/programme-versions")->assertStatus(401);
        $this->postJson("/api/projects/{$project->id}/programme-versions/preview", [])->assertStatus(401);
    }

    public function test_import_with_more_than_2000_activities_returns_422_and_leaves_no_leftover_files(): void
    {
        $project = $this->project();

        $rows = [['Activity Name', 'Duration']];
        for ($i = 0; $i < 2100; $i++) {
            $rows[] = ["Task {$i}", '1'];
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($rows as $r => $row) {
            foreach ($row as $c => $value) {
                $sheet->setCellValueByColumnAndRow($c + 1, $r + 1, $value);
            }
        }
        $path = sys_get_temp_dir().'/programme-api-big-'.uniqid().'.xlsx';
        $this->tempFiles[] = $path;
        (new Xlsx($spreadsheet))->save($path);

        $res = $this->actingAs($this->editor)
            ->post("/api/projects/{$project->id}/programme-versions/preview", [
                'file' => new UploadedFile($path, 'big.xlsx', null, null, true),
            ])
            ->assertOk();

        $preview = $res->json('data');

        $this->actingAs($this->editor)
            ->postJson("/api/projects/{$project->id}/programme-versions/import", [
                'token' => $preview['token'],
                'mapping' => [
                    'name' => $preview['suggested']['name'],
                    'duration' => $preview['suggested']['duration'],
                ],
                'label' => 'Too big',
            ])
            ->assertStatus(422);

        $this->assertEmpty(Storage::disk('local')->allFiles($this->dir($project->id)));
    }

    private function dir(int $projectId): string
    {
        return "programmes/{$projectId}";
    }

    public function test_csv_multipart_preview_and_import_succeeds(): void
    {
        $project = $this->project();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($this->sampleRows() as $r => $row) {
            foreach ($row as $c => $value) {
                $sheet->setCellValueByColumnAndRow($c + 1, $r + 1, $value);
            }
        }
        $path = sys_get_temp_dir().'/programme-api-csv-'.uniqid().'.csv';
        $this->tempFiles[] = $path;
        (new Csv($spreadsheet))->save($path);

        $previewRes = $this->actingAs($this->editor)
            ->post("/api/projects/{$project->id}/programme-versions/preview", [
                'file' => new UploadedFile($path, 'programme.csv', null, null, true),
            ])
            ->assertOk();

        $preview = $previewRes->json('data');
        $this->assertSame('Activity Name', $preview['headers'][0]);

        $importRes = $this->actingAs($this->editor)
            ->postJson("/api/projects/{$project->id}/programme-versions/import", [
                'token' => $preview['token'],
                'mapping' => [
                    'name' => $preview['suggested']['name'],
                    'duration' => $preview['suggested']['duration'],
                    'start' => $preview['suggested']['start'],
                    'finish' => $preview['suggested']['finish'],
                    'actual_pct' => $preview['suggested']['actual_pct'],
                    'plan_pct' => $preview['suggested']['plan_pct'],
                ],
                'label' => 'CSV Import',
            ])
            ->assertCreated();

        $version = ProjectProgrammeVersion::findOrFail($importRes->json('data.id'));
        $this->assertSame('xlsx', $version->source_type);
        $this->assertSame(3, $version->activity_count);
        Storage::disk('local')->assertExists($version->source_file_path);
    }

    public function test_deleting_a_programme_version_keeps_the_reports_2_5_snapshot_rows(): void
    {
        $project = $this->project();
        $period = ProjectProgressPeriod::create([
            'project_id' => $project->id, 'period_no' => 1, 'period_start' => '2026-01-01', 'period_end' => '2026-01-31',
            'physical_scheduled_pct' => 1, 'physical_actual_pct' => 1, 'financial_scheduled_pct' => 1, 'financial_actual_pct' => 1,
            'financial_actual_amount' => 1,
        ]);

        $version = ProjectProgrammeVersion::create([
            'project_id' => $project->id, 'label' => 'Baseline', 'status_date' => '2026-01-15',
            'source_type' => 'manual', 'is_current' => true, 'activity_count' => 1,
        ]);
        $version->activities()->create([
            'seq' => 1, 'outline_level' => 1, 'name' => 'Earthworks', 'duration_days' => 5,
            'start' => '2026-01-01', 'finish' => '2026-01-05', 'actual_pct' => 50, 'plan_pct' => 60, 'is_summary' => false,
        ]);

        /** @var MonthlyReport $report */
        $report = app(MonthlyReportService::class)->create($project->id, ['period_id' => $period->id], $this->editor->id);
        $report->sections()->where('key', '2.5')->update(['include' => true]);
        app(MonthlyReportService::class)->regenerate($report, '2.5');

        $snapshotBefore = $report->fresh()->sections()->where('key', '2.5')->first()->data;
        $this->assertNotEmpty($snapshotBefore['rows']);
        $this->assertSame('Earthworks', $snapshotBefore['rows'][0]['task']);

        $this->actingAs($this->editor)
            ->deleteJson("/api/projects/{$project->id}/programme-versions/{$version->id}")
            ->assertOk();

        $this->assertNull(ProjectProgrammeVersion::find($version->id));

        $snapshotAfter = $report->fresh()->sections()->where('key', '2.5')->first()->data;
        $this->assertSame($snapshotBefore, $snapshotAfter);

        $html = app(PdfExporter::class)->html($report->fresh(['sections', 'project', 'period']), ['2.5']);
        $this->assertStringContainsString('Earthworks', $html);
    }

    public function test_import_with_expired_token_returns_422_and_expired_message(): void
    {
        $project = $this->project();

        $expiredToken = Crypt::encryptString(json_encode([
            'path' => $this->dir($project->id).'/tmp-expired.xlsx',
            'file_name' => 'programme.xlsx',
            'issued_at' => now()->subDays(2)->timestamp,
        ]));

        $res = $this->actingAs($this->editor)
            ->postJson("/api/projects/{$project->id}/programme-versions/import", [
                'token' => $expiredToken,
                'mapping' => ['name' => 0],
                'label' => 'Expired',
            ])
            ->assertStatus(422);

        $this->assertSame('The preview has expired — upload the file again.', $res->json('message'));
    }

    public function test_preview_sweeps_stale_tmp_files_older_than_24_hours(): void
    {
        $project = $this->project();
        $dir = $this->dir($project->id);

        $stalePath = $dir.'/tmp-stale.xlsx';
        Storage::disk('local')->put($stalePath, 'stale content');
        touch(Storage::disk('local')->path($stalePath), time() - 90000);

        $freshPath = $dir.'/tmp-fresh.xlsx';
        Storage::disk('local')->put($freshPath, 'fresh content');

        $this->actingAs($this->editor)
            ->post("/api/projects/{$project->id}/programme-versions/preview", [
                'file' => $this->xlsxUploadedFile(),
            ])
            ->assertOk();

        Storage::disk('local')->assertMissing($stalePath);
        Storage::disk('local')->assertExists($freshPath);
    }

    public function test_index_and_activities_return_404_for_unknown_project(): void
    {
        $this->actingAs($this->editor)
            ->getJson('/api/projects/999999/programme-versions')
            ->assertStatus(404);

        $this->actingAs($this->editor)
            ->getJson('/api/projects/999999/programme-versions/1/activities')
            ->assertStatus(404);
    }
}
