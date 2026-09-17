<?php

namespace Tests\Feature\MonthlyReport;

use App\Models\MonthlyReport;
use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\ProjectParty;
use App\Models\ProjectProgressPeriod;
use App\Services\MonthlyReport\ReportContext;
use App\Services\MonthlyReport\SectionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractSectionsTest extends TestCase
{
    use RefreshDatabase;

    private function context(): ReportContext
    {
        $project = Project::create(['name' => 'RTB SG. MUAR', 'code' => 'JPS/IP/BPB/10/2025', 'status' => 'in_progress']);
        ProjectContract::create(['project_id' => $project->id, 'title' => 'Main', 'contract_no' => 'JPS/IP/BPB/10/2025', 'is_main' => true,
            'contract_sum' => 288000000, 'performance_bond_amount' => 14400000, 'duration_months' => 24, 'dlp_months' => 12, 'lad_per_day' => 52128,
            'possession_date' => '2025-10-31', 'completion_date' => '2027-10-31', 'dlp_start_date' => '2027-11-01', 'dlp_end_date' => '2028-11-01', 'cidb_registration' => 'TBA',
            'insurances' => [['type' => "Contractor's All Risk", 'insurer' => 'PACIFIC INSURANCE', 'policy_no' => 'CEC-E0039188-H1', 'period_from' => '2025-10-31', 'period_to' => '2027-10-31', 'maintenance_from' => '2027-11-01', 'maintenance_to' => '2029-02-12']]]);
        $owner = ProjectParty::create(['project_id' => $project->id, 'name' => 'BAHAGIAN PENGURUSAN BANJIR, JPS MALAYSIA', 'type' => 'client', 'report_role' => 'owner', 'address' => 'Aras 3, Blok A, Cyber 8', 'sort_order' => 0]);
        $owner->contacts()->create(['name' => 'Ir. Marenawati binti Abd Malek', 'email' => 'marenawati@water.gov.my']);
        ProjectParty::create(['project_id' => $project->id, 'name' => 'MULTI GREEN ENGINEERING SDN BHD', 'type' => 'main_contractor', 'report_role' => 'contractor', 'address' => 'No. 35, Segamat', 'sort_order' => 6]);
        $period = ProjectProgressPeriod::create(['project_id' => $project->id, 'period_no' => 3, 'period_start' => '2025-12-16', 'period_end' => '2026-01-15']);
        $report = MonthlyReport::create(['project_id' => $project->id, 'period_id' => $period->id, 'report_no' => 3, 'title' => 'Monthly Progress Report No.3', 'month_label' => 'January 2026']);

        return ReportContext::for($report);
    }

    public function test_cover_builder_collects_parties_and_report_meta(): void
    {
        $data = SectionRegistry::make('cover')->build($this->context());

        $this->assertSame(1, $data['schema']);
        $this->assertSame('03 (TIGA)', $data['report_no_words']);
        $this->assertSame('MULTI GREEN ENGINEERING SDN BHD', $data['contractor']['name']);
        $this->assertSame('BAHAGIAN PENGURUSAN BANJIR, JPS MALAYSIA', $data['client']['name']);
        $this->assertNull($data['consultant']);
        $this->assertSame('15 DEC 2025 – 15 JAN 2026', $data['period_label']);
        $this->assertCount(3, $data['signatories']);
    }

    public function test_project_information_rows_are_rendered_from_contract_particulars(): void
    {
        $rows = collect(SectionRegistry::make('1.1')->build($this->context())['rows'])->keyBy('label');

        $this->assertSame('RM 288,000,000.00 (Two Hundred Eighty-Eight Million Ringgit Only)', $rows['Contract Sum']['value']);
        $this->assertSame('24 MONTHS', $rows['Duration of Completion']['value']);
        $this->assertSame('RM 52,128.00/day', $rows['LAD']['value']);
        $this->assertStringContainsString('Possession Date: 31/10/2025', $rows['Date Contract']['value']);
        $this->assertStringContainsString('PACIFIC INSURANCE', $rows['Insurance']['value']);
    }

    public function test_contract_correspondence_lists_parties_with_contacts(): void
    {
        $data = SectionRegistry::make('1.2')->build($this->context());

        $this->assertSame('Project Owner', $data['rows'][0]['party']);
        $this->assertSame('marenawati@water.gov.my', $data['rows'][0]['contacts'][0]['email']);
        $this->assertSame('Contractor', $data['rows'][1]['party']);
    }

    public function test_registry_lists_every_key_in_order(): void
    {
        $this->assertSame(['cover', '1.1', '1.2', '1.3', '1.4', '1.5', '2.1', '2.2', '2.3', '2.4', '2.5', '2.6', '3.1', '3.2', '3.4', '3.6', '3.7', '4.1', '4.2', '4.3', '5.0'], array_keys(SectionRegistry::all()));
    }
}
