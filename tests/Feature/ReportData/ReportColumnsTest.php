<?php

namespace Tests\Feature\ReportData;

use App\Models\CorrespondenceType;
use App\Models\Drawing;
use App\Models\Project;
use App\Models\ProjectInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportColumnsTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['projects.view', 'projects.edit', 'drawings.view'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->editor = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $this->editor->givePermissionTo(['projects.view', 'projects.edit', 'drawings.view']);
    }

    public function test_claim_report_columns_are_saved(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
        $claim = ProjectInvoice::create(['project_id' => $project->id, 'type' => 'client', 'party_name' => 'JPS', 'amount' => 1000, 'status' => 'draft', 'invoice_date' => now()->toDateString()]);

        $this->actingAs($this->editor)->putJson("/api/project-invoices/{$claim->id}", [
            'wjp_current' => 50, 'wjp_cumulative' => 150, 'certified_current' => 900, 'certified_cumulative' => 2700, 'evaluation_date' => '2026-01-20',
        ])->assertOk();

        $this->assertEquals(2700, $claim->fresh()->certified_cumulative);
    }

    public function test_correspondence_types_for_the_report_exist_with_groups(): void
    {
        $this->assertSame('adm', CorrespondenceType::where('code', 'adm')->value('report_group'));
        $this->assertSame('ma', CorrespondenceType::where('code', 'ma')->value('report_group'));
        $this->assertSame('mos', CorrespondenceType::where('code', 'mos')->value('report_group'));
        $this->assertSame('rfi', CorrespondenceType::where('code', 'rfi')->value('report_group'));
        $this->assertSame('ncr', CorrespondenceType::where('code', 'ncr')->value('report_group'));
        $this->assertNull(CorrespondenceType::where('code', 'rfa')->value('report_group'));
    }

    public function test_tender_drawings_can_be_flagged_and_filtered(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
        $tender = Drawing::create(['title' => 'KEY PLAN', 'drawing_no' => 'JPS/IP/BPB/10/2025/GEN/03', 'project_id' => $project->id, 'is_tender' => true, 'status' => 'published', 'file_path' => 'drawings/a.pdf', 'file_name' => 'a.pdf']);
        Drawing::create(['title' => 'Shop', 'drawing_no' => 'SD/01', 'project_id' => $project->id, 'status' => 'published', 'file_path' => 'drawings/b.pdf', 'file_name' => 'b.pdf']);

        $res = $this->actingAs($this->editor)->getJson("/api/drawings?project_id={$project->id}&is_tender=1")->assertOk();
        $ids = collect($res->json('data.data') ?? $res->json('data'))->pluck('id')->all();
        $this->assertSame([$tender->id], $ids);
    }
}
