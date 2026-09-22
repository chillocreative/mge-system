<?php

namespace Tests\Feature;

use App\Models\EnvironmentProjectSetting;
use App\Models\EnvironmentReport;
use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\ProjectParty;
use App\Models\User;
use App\Models\WaterQualityRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EnvironmentReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('environmental.view', 'web');
        Permission::findOrCreate('environmental.create', 'web');
        Permission::findOrCreate('environmental.manage', 'web');
    }

    private function actor(array $permissions = ['environmental.view', 'environmental.create', 'environmental.manage']): User
    {
        $u = User::create([
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'u-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('x'),
            'status' => 'active',
        ]);
        $u->givePermissionTo($permissions);

        return $u;
    }

    private function project(): Project
    {
        return Project::create([
            'name' => 'River Project',
            'code' => 'PR'.random_int(1000, 9999),
            'status' => 'in_progress',
            'start_date' => '2025-11-01',
            'end_date' => '2027-10-31',
        ]);
    }

    private function contract(Project $project): ProjectContract
    {
        return ProjectContract::create([
            'project_id' => $project->id,
            'title' => 'Main Contract',
            'contract_no' => 'JPS/IP/BPB/10/2025',
            'contract_value' => 288000000,
            'is_main' => true,
            'contract_sum' => 288000000,
            'duration_months' => 24,
            'dlp_months' => 12,
            'possession_date' => '2025-10-31',
            'completion_date' => '2027-10-31',
            'dlp_start_date' => '2027-11-01',
            'dlp_end_date' => '2028-11-01',
            'start_date' => '2025-10-31',
            'end_date' => '2027-10-31',
        ]);
    }

    private function seedParties(Project $project): void
    {
        ProjectParty::create(['project_id' => $project->id, 'name' => 'Owner Sdn Bhd', 'report_role' => 'owner', 'sort_order' => 1]);
        ProjectParty::create(['project_id' => $project->id, 'name' => 'SO Department', 'report_role' => 'superintending_officer', 'sort_order' => 2]);
        ProjectParty::create(['project_id' => $project->id, 'name' => 'Lukman Rosli Resources', 'report_role' => 'consultant', 'sort_order' => 3]);
        ProjectParty::create(['project_id' => $project->id, 'name' => 'Multi Green Engineering Sdn Bhd', 'report_role' => 'contractor', 'sort_order' => 4]);
    }

    private function seedSettings(Project $project): void
    {
        EnvironmentProjectSetting::create([
            'project_id' => $project->id,
            'consultant_company' => 'Lukman Rosli Resources',
            'consultant_name' => 'Mohamad Lukman Bin Rosli',
            'consultant_reg_no' => 'CePEOEIA/00001',
            'officer_name' => 'Noralina Binti Rosli',
            'officer_reg_no' => 'TBA',
        ]);
    }

    public function test_store_builds_defaults_with_incrementing_report_no(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->contract($project);
        $this->seedParties($project);
        $this->seedSettings($project);

        $response = $this->actingAs($actor)->postJson('/api/environment/reports', [
            'project_id' => $project->id,
            'period_start' => '2025-12-15',
            'period_end' => '2026-01-15',
        ]);

        $response->assertCreated();
        $this->assertSame(1, $response->json('data.report_no'));
        $this->assertSame('MONTHLY ENVIRONMENT REPORT NO.1', $response->json('data.title'));
        $this->assertSame('JPS/IP/BPB/10/2025', $response->json('data.sections.contract.rows.1.value'));
        $this->assertSame('Owner Sdn Bhd', $response->json('data.sections.flow_chart.proponent.lines.0'));
        $this->assertSame('Lukman Rosli Resources', $response->json('data.sections.ems.consultant_company'));
        $this->assertSame('Mohamad Lukman Bin Rosli', $response->json('data.sections.ems.consultant_name'));
        $this->assertSame('Mohamad Lukman Bin Rosli', $response->json('data.signatories.1.name'));

        $second = $this->actingAs($actor)->postJson('/api/environment/reports', [
            'project_id' => $project->id,
            'period_start' => '2026-01-16',
            'period_end' => '2026-02-15',
        ]);
        $second->assertCreated();
        $this->assertSame(2, $second->json('data.report_no'));
        $this->assertSame('MONTHLY ENVIRONMENT REPORT NO.2', $second->json('data.title'));
    }

    public function test_parameters_periods_and_results_reflect_water_quality_records(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->contract($project);
        $this->seedParties($project);
        $this->seedSettings($project);

        WaterQualityRecord::create([
            'project_id' => $project->id,
            'sample_date' => '2025-12-20',
            'insitu' => ['W1' => ['temperature' => '27.5', 'ph' => '7.1', 'do' => '5.2']],
            'lab' => ['W1' => ['cod' => '10', 'bod' => '2']],
        ]);

        $response = $this->actingAs($actor)->postJson('/api/environment/reports', [
            'project_id' => $project->id,
            'period_start' => '2025-12-15',
            'period_end' => '2026-01-15',
        ]);

        $response->assertCreated();
        $periods = $response->json('data.sections.parameters.periods');

        // Spans from contract start month (Oct 2025) through period_end month (Jan 2026).
        $this->assertSame('October 2025', $periods[0]['session']);
        $this->assertSame('January 2026', $periods[array_key_last($periods)]['session']);

        $decemberPeriod = collect($periods)->firstWhere('session', 'December 2025');
        $this->assertSame('✓', $decemberPeriod['water']);

        $novemberPeriod = collect($periods)->firstWhere('session', 'November 2025');
        $this->assertSame('-', $novemberPeriod['water']);

        $rows = collect($response->json('data.sections.results.rows'));
        $temp = $rows->firstWhere('parameter', 'Temperature (In-situ)');
        $this->assertSame('27.5', $temp['w1']);
        $cod = $rows->firstWhere('parameter', 'Chemical Oxygen Demand (COD)');
        $this->assertSame('10', $cod['w1']);
    }

    public function test_update_replaces_only_the_given_section_and_keeps_signatories(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->contract($project);
        $this->seedParties($project);
        $this->seedSettings($project);

        $report = EnvironmentReport::findOrFail(
            $this->actingAs($actor)->postJson('/api/environment/reports', [
                'project_id' => $project->id,
                'period_start' => '2025-12-15',
                'period_end' => '2026-01-15',
            ])->json('data.id')
        );

        $originalContract = $report->sections['contract'];
        $originalSignatories = $report->signatories;

        $response = $this->actingAs($actor)->putJson("/api/environment/reports/{$report->id}", [
            'sections' => [
                'bmp' => ['intro' => 'Custom intro', 'items' => []],
            ],
        ]);

        $response->assertOk();
        $this->assertSame(['intro' => 'Custom intro', 'items' => []], $response->json('data.sections.bmp'));
        $this->assertSame($originalContract, $response->json('data.sections.contract'));
        $this->assertSame($originalSignatories, $response->json('data.signatories'));
    }

    /**
     * Pins the literal status string the API stores and returns. The UI gates its read-only mode,
     * its Reopen button and its list filter on this exact value; it read 'final' while the API
     * wrote 'finalised', so no report ever appeared locked. Change this value and the frontend
     * constant REPORT_STATUS_FINAL in resources/js/services/environmentReportService.js together.
     */
    public function test_finalise_sets_the_status_string_the_frontend_matches_on(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->contract($project);
        $this->seedParties($project);
        $this->seedSettings($project);

        $created = $this->actingAs($actor)->postJson('/api/environment/reports', [
            'project_id' => $project->id,
            'period_start' => '2025-12-15',
            'period_end' => '2026-01-15',
        ]);
        $this->assertSame('draft', $created->json('data.status'));
        $reportId = $created->json('data.id');

        $finalised = $this->actingAs($actor)->postJson("/api/environment/reports/{$reportId}/finalise");
        $finalised->assertOk();
        $this->assertSame('finalised', $finalised->json('data.status'));

        // And the list endpoint filters on the same spelling.
        $filtered = $this->actingAs($actor)->getJson('/api/environment/reports?status=finalised');
        $filtered->assertOk();
        $this->assertContains($reportId, array_column($filtered->json('data.data') ?? $filtered->json('data'), 'id'));
    }

    public function test_regenerate_restores_defaults_and_finalised_report_rejects_update(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->contract($project);
        $this->seedParties($project);
        $this->seedSettings($project);

        $reportId = $this->actingAs($actor)->postJson('/api/environment/reports', [
            'project_id' => $project->id,
            'period_start' => '2025-12-15',
            'period_end' => '2026-01-15',
        ])->json('data.id');

        $this->actingAs($actor)->putJson("/api/environment/reports/{$reportId}", [
            'sections' => ['contract' => ['rows' => []]],
        ])->assertOk();

        $regen = $this->actingAs($actor)->postJson("/api/environment/reports/{$reportId}/regenerate/contract");
        $regen->assertOk();
        $this->assertNotEmpty($regen->json('data.sections.contract.rows'));

        $this->actingAs($actor)->postJson("/api/environment/reports/{$reportId}/finalise")->assertOk();

        $this->actingAs($actor)->putJson("/api/environment/reports/{$reportId}", ['title' => 'Edited'])
            ->assertStatus(422);

        $this->actingAs($actor)->postJson("/api/environment/reports/{$reportId}/regenerate/contract")
            ->assertStatus(422);
    }

    public function test_asset_upload_download_and_delete(): void
    {
        Storage::fake('local');

        $actor = $this->actor();
        $project = $this->project();
        $this->contract($project);
        $this->seedParties($project);
        $this->seedSettings($project);

        $reportId = $this->actingAs($actor)->postJson('/api/environment/reports', [
            'project_id' => $project->id,
            'period_start' => '2025-12-15',
            'period_end' => '2026-01-15',
        ])->json('data.id');

        $file = UploadedFile::fake()->image('wash-trough.jpg');

        $upload = $this->actingAs($actor)->postJson("/api/environment/reports/{$reportId}/assets", [
            'kind' => 'bmp_photo',
            'caption' => 'Wash Trough',
            'file' => $file,
        ]);
        $upload->assertCreated();
        $assetId = $upload->json('data.id');

        $download = $this->actingAs($actor)->get("/api/environment/reports/assets/{$assetId}/download");
        $download->assertOk();
        $this->assertSame('image/jpeg', $download->headers->get('Content-Type'));

        $this->actingAs($actor)->deleteJson("/api/environment/reports/assets/{$assetId}")->assertOk();
        $this->assertDatabaseMissing('environment_report_assets', ['id' => $assetId]);
    }

    public function test_view_only_user_gets_403_on_store(): void
    {
        $actor = $this->actor(['environmental.view']);
        $project = $this->project();

        $response = $this->actingAs($actor)->postJson('/api/environment/reports', [
            'project_id' => $project->id,
            'period_start' => '2025-12-15',
            'period_end' => '2026-01-15',
        ]);

        $response->assertStatus(403);
    }

    // ── Item 1: report_no counts soft-deleted rows and is race-safe ──

    public function test_report_no_skips_deleted_reports(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->contract($project);
        $this->seedParties($project);
        $this->seedSettings($project);

        // Create first two reports
        $first = $this->actingAs($actor)->postJson('/api/environment/reports', [
            'project_id' => $project->id,
            'period_start' => '2025-12-15',
            'period_end' => '2026-01-15',
        ]);
        $first->assertCreated();
        $this->assertSame(1, $first->json('data.report_no'));

        $second = $this->actingAs($actor)->postJson('/api/environment/reports', [
            'project_id' => $project->id,
            'period_start' => '2026-01-16',
            'period_end' => '2026-02-15',
        ]);
        $second->assertCreated();
        $this->assertSame(2, $second->json('data.report_no'));

        // Delete the second report (soft delete)
        $this->actingAs($actor)->deleteJson("/api/environment/reports/{$second->json('data.id')}")->assertOk();

        // Create third report - should get report_no=3, not 2
        $third = $this->actingAs($actor)->postJson('/api/environment/reports', [
            'project_id' => $project->id,
            'period_start' => '2026-02-16',
            'period_end' => '2026-03-15',
        ]);
        $third->assertCreated();
        $this->assertSame(3, $third->json('data.report_no'));
    }

    // ── Item 2: finalised lock covers assets and deletion ──

    public function test_finalised_report_rejects_asset_operations(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->contract($project);
        $this->seedParties($project);
        $this->seedSettings($project);

        Storage::fake('local');

        $reportId = $this->actingAs($actor)->postJson('/api/environment/reports', [
            'project_id' => $project->id,
            'period_start' => '2025-12-15',
            'period_end' => '2026-01-15',
        ])->json('data.id');

        // Finalise the report
        $this->actingAs($actor)->postJson("/api/environment/reports/{$reportId}/finalise")->assertOk();

        $file = UploadedFile::fake()->image('test.jpg');

        // Add asset should fail
        $this->actingAs($actor)->postJson("/api/environment/reports/{$reportId}/assets", [
            'kind' => 'bmp_photo',
            'file' => $file,
        ])->assertStatus(422);

        // Update asset should fail
        $existingAsset = \App\Models\EnvironmentReportAsset::create([
            'environment_report_id' => $reportId,
            'kind' => 'bmp_photo',
            'caption' => 'Old caption',
            'sort_order' => 1,
            'file_path' => 'test.jpg',
            'file_name' => 'test.jpg',
            'file_type' => 'jpg',
            'file_size' => 1000,
        ]);

        $this->actingAs($actor)->putJson("/api/environment/reports/assets/{$existingAsset->id}", [
            'caption' => 'New caption',
            'sort_order' => 2,
        ])->assertStatus(422);

        // Delete asset should fail
        $this->actingAs($actor)->deleteJson("/api/environment/reports/assets/{$existingAsset->id}")->assertStatus(422);

        // Delete report should fail
        $this->actingAs($actor)->deleteJson("/api/environment/reports/{$reportId}")->assertStatus(422);
    }

    public function test_asset_operations_succeed_after_reopen(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->contract($project);
        $this->seedParties($project);
        $this->seedSettings($project);

        Storage::fake('local');

        $reportId = $this->actingAs($actor)->postJson('/api/environment/reports', [
            'project_id' => $project->id,
            'period_start' => '2025-12-15',
            'period_end' => '2026-01-15',
        ])->json('data.id');

        $this->actingAs($actor)->postJson("/api/environment/reports/{$reportId}/finalise")->assertOk();

        $existingAsset = \App\Models\EnvironmentReportAsset::create([
            'environment_report_id' => $reportId,
            'kind' => 'bmp_photo',
            'caption' => 'Old caption',
            'sort_order' => 1,
            'file_path' => 'test.jpg',
            'file_name' => 'test.jpg',
            'file_type' => 'jpg',
            'file_size' => 1000,
        ]);

        // Reopen allows operations again
        $this->actingAs($actor)->postJson("/api/environment/reports/{$reportId}/reopen")->assertOk();

        $updateResp = $this->actingAs($actor)->putJson("/api/environment/reports/assets/{$existingAsset->id}", [
            'caption' => 'Updated caption',
        ]);
        $updateResp->assertOk();
        $this->assertSame('Updated caption', $updateResp->json('data.caption'));

        $this->actingAs($actor)->deleteJson("/api/environment/reports/assets/{$existingAsset->id}")->assertOk();
        $this->actingAs($actor)->deleteJson("/api/environment/reports/{$reportId}")->assertOk();
    }

    // ── Item 3: assets resolve through report (cannot access deleted report's assets) ──

    public function test_asset_access_fails_for_soft_deleted_report(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->contract($project);
        $this->seedParties($project);
        $this->seedSettings($project);

        Storage::fake('local');

        $reportId = $this->actingAs($actor)->postJson('/api/environment/reports', [
            'project_id' => $project->id,
            'period_start' => '2025-12-15',
            'period_end' => '2026-01-15',
        ])->json('data.id');

        $file = UploadedFile::fake()->image('test.jpg');
        $upload = $this->actingAs($actor)->postJson("/api/environment/reports/{$reportId}/assets", [
            'kind' => 'bmp_photo',
            'file' => $file,
        ]);
        $assetId = $upload->json('data.id');

        // Access while report exists - OK
        $this->actingAs($actor)->get("/api/environment/reports/assets/{$assetId}/download")->assertOk();

        // Soft-delete the report
        $this->actingAs($actor)->deleteJson("/api/environment/reports/{$reportId}")->assertOk();

        // Now asset endpoints should return 404 because report doesn't exist
        $this->actingAs($actor)->get("/api/environment/reports/assets/{$assetId}/download")->assertStatus(404);
        $this->actingAs($actor)->putJson("/api/environment/reports/assets/{$assetId}", [
            'caption' => 'Changed',
        ])->assertStatus(404);
        $this->actingAs($actor)->deleteJson("/api/environment/reports/assets/{$assetId}")->assertStatus(404);
    }

    // ── Item 6: sort_order max constraint ──

    public function test_sort_order_rejects_above_65535(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->contract($project);
        $this->seedParties($project);
        $this->seedSettings($project);

        $reportId = $this->actingAs($actor)->postJson('/api/environment/reports', [
            'project_id' => $project->id,
            'period_start' => '2025-12-15',
            'period_end' => '2026-01-15',
        ])->json('data.id');

        $file = UploadedFile::fake()->image('test.jpg');
        $upload = $this->actingAs($actor)->postJson("/api/environment/reports/{$reportId}/assets", [
            'kind' => 'bmp_photo',
            'file' => $file,
        ]);
        $assetId = $upload->json('data.id');

        // Try to set invalid sort_order
        $this->actingAs($actor)->putJson("/api/environment/reports/assets/{$assetId}", [
            'sort_order' => 99999999,
        ])->assertStatus(422);

        // Valid max value should work
        $this->actingAs($actor)->putJson("/api/environment/reports/assets/{$assetId}", [
            'sort_order' => 65535,
        ])->assertOk();
    }

    // ── Item 4: period invariant in update ──

    public function test_update_rejects_period_end_before_period_start(): void
    {
        $actor = $this->actor();
        $project = $this->project();
        $this->contract($project);
        $this->seedParties($project);
        $this->seedSettings($project);

        $reportId = $this->actingAs($actor)->postJson('/api/environment/reports', [
            'project_id' => $project->id,
            'period_start' => '2025-12-15',
            'period_end' => '2026-01-15',
        ])->json('data.id');

        // Try swapping dates
        $this->actingAs($actor)->putJson("/api/environment/reports/{$reportId}", [
            'period_start' => '2026-01-15',
            'period_end' => '2025-12-15',
        ])->assertStatus(422);

        // Same dates should be valid
        $this->actingAs($actor)->putJson("/api/environment/reports/{$reportId}", [
            'period_start' => '2025-12-15',
            'period_end' => '2025-12-15',
        ])->assertOk();
    }
}
