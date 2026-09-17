<?php

namespace Tests\Feature\MonthlyReport;

use App\Models\MonthlyReport;
use App\Models\Project;
use App\Models\ProjectInvoice;
use App\Models\ProjectParty;
use App\Models\ProjectProgressPeriod;
use App\Models\ProjectScheduleBaseline;
use App\Models\User;
use App\Services\MonthlyReport\ReportContext;
use App\Services\MonthlyReport\SectionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressSectionsTest extends TestCase
{
    use RefreshDatabase;

    private function context(): ReportContext
    {
        $project = Project::create(['name' => 'RTB SG. MUAR', 'code' => 'JPS/IP/BPB/10/2025', 'status' => 'in_progress']);

        ProjectParty::create(['project_id' => $project->id, 'name' => 'MULTI GREEN ENGINEERING SDN BHD', 'type' => 'main_contractor', 'report_role' => 'contractor', 'address' => 'No. 35, Segamat', 'sort_order' => 6]);

        // Chain of 3 members: PM -> Site Manager -> Site Supervisor A, plus a 4th
        // member (Site Supervisor B) also under Site Manager, so root has one
        // child while there are still two Site Supervisors overall.
        $pm = User::create(['first_name' => 'Norazlinda', 'last_name' => 'Sabarudin', 'email' => 'pm-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $manager = User::create(['first_name' => 'Ahmad', 'last_name' => 'Haziq', 'email' => 'sm-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $supervisorA = User::create(['first_name' => 'Ali', 'last_name' => 'Hassan', 'email' => 'ss1-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $supervisorB = User::create(['first_name' => 'Bakar', 'last_name' => 'Osman', 'email' => 'ss2-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);

        $project->members()->attach([
            $pm->id => ['role' => 'member'],
            $manager->id => ['role' => 'member'],
            $supervisorA->id => ['role' => 'member'],
            $supervisorB->id => ['role' => 'member'],
        ]);
        $project->members()->updateExistingPivot($pm->id, ['designation' => 'Project Manager', 'reports_to_user_id' => null, 'org_sort' => 0]);
        $project->members()->updateExistingPivot($manager->id, ['designation' => 'Site Manager', 'reports_to_user_id' => $pm->id, 'org_sort' => 1]);
        $project->members()->updateExistingPivot($supervisorA->id, ['designation' => 'Site Supervisor', 'reports_to_user_id' => $manager->id, 'org_sort' => 2]);
        $project->members()->updateExistingPivot($supervisorB->id, ['designation' => 'Site Supervisor', 'reports_to_user_id' => $manager->id, 'org_sort' => 3]);

        foreach ([['2025-10-01', 0], ['2025-11-01', 1], ['2025-12-01', 1], ['2026-01-01', 2]] as [$month, $pct]) {
            ProjectScheduleBaseline::create(['project_id' => $project->id, 'month' => $month, 'scheduled_physical_pct' => $pct, 'scheduled_financial_amount' => $pct * 100000, 'scheduled_financial_pct' => $pct]);
        }

        ProjectProgressPeriod::create(['project_id' => $project->id, 'period_no' => 2, 'period_start' => '2025-11-16', 'period_end' => '2025-12-15', 'physical_scheduled_pct' => 1, 'physical_actual_pct' => 1, 'financial_scheduled_pct' => 1, 'financial_actual_pct' => 1, 'financial_actual_amount' => 100000]);
        $period3 = ProjectProgressPeriod::create(['project_id' => $project->id, 'period_no' => 3, 'period_start' => '2025-12-16', 'period_end' => '2026-01-15', 'planning_days_completion' => 497, 'physical_scheduled_pct' => 2, 'physical_actual_pct' => 4, 'financial_scheduled_pct' => 2, 'financial_actual_pct' => 4, 'financial_actual_amount' => 400000]);

        ProjectInvoice::create(['project_id' => $project->id, 'type' => 'client', 'claim_number' => '1', 'invoice_date' => '2025-12-20', 'evaluation_date' => '2025-12-22', 'amount' => 100000, 'status' => 'paid', 'certified_current' => 95000, 'certified_cumulative' => 95000, 'wjp_current' => 90000, 'wjp_cumulative' => 90000, 'payment_cert_date' => '2025-12-28']);
        ProjectInvoice::create(['project_id' => $project->id, 'type' => 'client', 'claim_number' => '2', 'invoice_date' => '2026-01-20', 'evaluation_date' => '2026-01-22', 'amount' => 200000, 'status' => 'submitted', 'wjp_current' => 80000, 'wjp_cumulative' => 170000]);

        $report = MonthlyReport::create(['project_id' => $project->id, 'period_id' => $period3->id, 'report_no' => 3, 'title' => 'Monthly Progress Report No.3', 'month_label' => 'January 2026']);

        return ReportContext::for($report);
    }

    public function test_organisation_chart_builds_a_tree_with_a_single_root_child(): void
    {
        $data = SectionRegistry::make('1.4')->build($this->context());

        $this->assertSame(1, $data['schema']);
        $this->assertSame('MULTI GREEN ENGINEERING SDN BHD', $data['company']);
        $this->assertCount(1, $data['tree']);
        $this->assertSame('Project Manager', $data['tree'][0]['designation']);
        $this->assertCount(1, $data['tree'][0]['children']);
        $this->assertSame('Site Manager', $data['tree'][0]['children'][0]['designation']);
        $this->assertCount(2, $data['tree'][0]['children'][0]['children']);
    }

    public function test_resource_planning_groups_members_by_designation(): void
    {
        $rows = SectionRegistry::make('1.5')->build($this->context())['rows'];

        $this->assertContains(['designation' => 'Site Supervisor', 'nos' => 2], $rows);
    }

    public function test_work_progress_summarises_physical_and_financial_rows(): void
    {
        $data = SectionRegistry::make('2.1')->build($this->context());

        $physical = collect($data['physical']['rows'])->keyBy('label');
        $this->assertSame('1%', $physical['Scheduled Progress']['prev']);
        $this->assertSame('2%', $physical['Scheduled Progress']['cur']);
        $this->assertSame('1%', $physical['Actual Progress']['prev']);
        $this->assertSame('4%', $physical['Actual Progress']['cur']);
        $this->assertSame('0%', $physical['Variance (+ / -)']['prev']);
        $this->assertSame('+2%', $physical['Variance (+ / -)']['cur']);
        $this->assertSame('0', $physical['Ahead /Delay in Day']['prev']);
        $this->assertSame('10 Days', $physical['Ahead /Delay in Day']['cur']);
        $this->assertSame('ON TRACK', $physical['Status']['prev']);
        $this->assertSame('AHEAD', $physical['Status']['cur']);
    }

    public function test_physical_s_curve_series_matches_baseline_months(): void
    {
        $data = SectionRegistry::make('2.2')->build($this->context());

        $this->assertSame(['Oct-25', 'Nov-25', 'Dec-25', 'Jan-26'], $data['series']['months']);
        $this->assertSame([null, null, 1.0, 4.0], $data['series']['actual']);
    }

    public function test_progress_claim_lists_invoices_in_claim_order(): void
    {
        $rows = SectionRegistry::make('2.3')->build($this->context())['rows'];

        $this->assertCount(2, $rows);
        $this->assertSame('1', $rows[0]['ipc_no']);
        $this->assertSame('2', $rows[1]['ipc_no']);
    }

    public function test_financial_s_curve_series_has_scheduled_amounts_for_every_month(): void
    {
        $data = SectionRegistry::make('2.4')->build($this->context());

        $this->assertCount(4, $data['series']['scheduled']);
    }

    public function test_physical_s_curve_uses_the_later_period_when_two_periods_end_in_the_same_month(): void
    {
        $project = Project::create(['name' => 'Tie Test', 'code' => 'TIE-'.uniqid(), 'status' => 'in_progress']);
        ProjectScheduleBaseline::create(['project_id' => $project->id, 'month' => '2025-12-01', 'scheduled_physical_pct' => 1, 'scheduled_financial_amount' => 100000, 'scheduled_financial_pct' => 1]);
        ProjectProgressPeriod::create(['project_id' => $project->id, 'period_no' => 1, 'period_start' => '2025-12-01', 'period_end' => '2025-12-10', 'physical_scheduled_pct' => 1, 'physical_actual_pct' => 1]);
        $later = ProjectProgressPeriod::create(['project_id' => $project->id, 'period_no' => 2, 'period_start' => '2025-12-11', 'period_end' => '2025-12-20', 'physical_scheduled_pct' => 1, 'physical_actual_pct' => 3]);
        $report = MonthlyReport::create(['project_id' => $project->id, 'period_id' => $later->id, 'report_no' => 1, 'title' => 'Tie Report', 'month_label' => 'December 2025']);

        $data = SectionRegistry::make('2.2')->build(ReportContext::for($report));

        $this->assertSame([3.0], $data['series']['actual']);
    }
}
