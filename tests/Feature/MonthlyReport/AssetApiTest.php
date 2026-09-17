<?php

namespace Tests\Feature\MonthlyReport;

use App\Models\MonthlyReport;
use App\Models\MonthlyReportAsset;
use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\ProjectParty;
use App\Models\ProjectProgressPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AssetApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['reports.view', 'reports.manage', 'projects.view'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = User::create([
            'first_name' => 'U', 'last_name' => 'Sr', 'email' => 'u-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('x'), 'status' => 'active',
        ]);
        $user->givePermissionTo($permissions);

        return $user;
    }

    /** @return array{0: User, 1: MonthlyReport} */
    private function managerAndReport(): array
    {
        $manager = $this->userWithPermissions(['reports.view', 'reports.manage', 'projects.view']);

        $project = Project::create(['name' => 'RTB SG. MUAR', 'code' => 'JPS/IP/BPB/'.uniqid(), 'status' => 'in_progress', 'report_cutoff_day' => 15]);
        ProjectContract::create(['project_id' => $project->id, 'title' => 'Main', 'is_main' => true, 'contract_sum' => 288000000]);
        ProjectParty::create(['project_id' => $project->id, 'name' => 'MULTI GREEN ENGINEERING SDN BHD', 'type' => 'main_contractor', 'report_role' => 'contractor', 'address' => 'No. 35, Segamat', 'sort_order' => 6]);
        $period = ProjectProgressPeriod::create([
            'project_id' => $project->id, 'period_no' => 3, 'period_start' => '2025-12-16', 'period_end' => '2026-01-15',
            'planning_days_completion' => 497, 'physical_scheduled_pct' => 2, 'physical_actual_pct' => 4,
            'financial_scheduled_pct' => 2, 'financial_actual_pct' => 4, 'financial_actual_amount' => 400000,
        ]);

        $reportId = $this->actingAs($manager)->postJson("/api/projects/{$project->id}/monthly-reports", [
            'period_id' => $period->id,
        ])->json('data.id');

        return [$manager, MonthlyReport::find($reportId)];
    }

    public function test_upload_png_gantt_page_and_list(): void
    {
        Storage::fake('local');
        [$manager, $report] = $this->managerAndReport();

        $res = $this->actingAs($manager)->postJson("/api/monthly-reports/{$report->id}/assets", [
            'file' => UploadedFile::fake()->create('gantt-1.png', 120, 'image/png'),
        ]);

        $res->assertCreated()->assertJsonPath('data.kind', 'gantt_page')->assertJsonPath('data.extension', 'png');
        Storage::disk('local')->assertExists($res->json('data.file_path'));
        $this->actingAs($manager)->getJson("/api/monthly-reports/{$report->id}/assets")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_upload_pdf_probes_readability_and_stores_page_count(): void
    {
        Storage::fake('local');
        [$manager, $report] = $this->managerAndReport();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML('<p>a</p><div style="page-break-before:always">b</div>')->output();

        $res = $this->actingAs($manager)->postJson("/api/monthly-reports/{$report->id}/assets", [
            'file' => UploadedFile::fake()->createWithContent('gantt.pdf', $pdf),
        ]);

        $res->assertCreated()->assertJsonPath('data.pages', 2);
    }

    public function test_unreadable_pdf_is_rejected_with_guidance(): void
    {
        Storage::fake('local');
        [$manager, $report] = $this->managerAndReport();

        $this->actingAs($manager)->postJson("/api/monthly-reports/{$report->id}/assets", [
            'file' => UploadedFile::fake()->createWithContent('bad.pdf', 'not a pdf'),
        ])->assertStatus(422)->assertJsonFragment(['message' => 'This PDF uses a compression the report merger cannot read. Re-save it as PDF 1.4 (Print to PDF) or upload PNG/JPG pages instead.']);
    }

    public function test_asset_from_another_report_is_404_and_finalised_report_rejects_writes(): void
    {
        Storage::fake('local');
        [$manager, $report] = $this->managerAndReport();
        [$_, $other] = $this->managerAndReport();
        $asset = MonthlyReportAsset::create(['report_id' => $other->id, 'kind' => 'gantt_page', 'file_path' => 'x.png', 'file_name' => 'x.png', 'sort_order' => 1]);

        $this->actingAs($manager)->deleteJson("/api/monthly-reports/{$report->id}/assets/{$asset->id}")->assertNotFound();

        $report->update(['status' => 'final']);
        $this->actingAs($manager)->postJson("/api/monthly-reports/{$report->id}/assets", [
            'file' => UploadedFile::fake()->create('g.png', 10, 'image/png'),
        ])->assertStatus(422);
    }

    public function test_viewer_cannot_upload_but_can_list(): void
    {
        Storage::fake('local');
        [$manager, $report] = $this->managerAndReport();
        $viewer = $this->userWithPermissions(['reports.view']);

        $this->actingAs($viewer)->getJson("/api/monthly-reports/{$report->id}/assets")->assertOk();
        $this->actingAs($viewer)->postJson("/api/monthly-reports/{$report->id}/assets", [
            'file' => UploadedFile::fake()->create('g.png', 10, 'image/png'),
        ])->assertForbidden();
    }

    public function test_pdf_export_appends_gantt_pages_after_section_2_5(): void
    {
        Storage::fake('local');
        [$manager, $report] = $this->managerAndReport();
        $this->actingAs($manager)->postJson("/api/monthly-reports/{$report->id}/assets", [
            'file' => UploadedFile::fake()->createWithContent('gantt.pdf', \Barryvdh\DomPDF\Facade\Pdf::loadHTML('<p>GANTT-PAGE</p>')->output()),
        ])->assertCreated();

        $before = preg_match_all('/\/Type\s*\/Page[^s]/', app(\App\Services\MonthlyReport\Export\PdfExporter::class)->render($report->fresh(['sections', 'project', 'period'])));
        MonthlyReportAsset::where('report_id', $report->id)->delete();
        $after = preg_match_all('/\/Type\s*\/Page[^s]/', app(\App\Services\MonthlyReport\Export\PdfExporter::class)->render($report->fresh(['sections', 'project', 'period'])));

        $this->assertSame($after + 1, $before);
    }
}
