<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectExpense;
use App\Models\ProjectSubcontractorClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The Reports chart must place every amount in its real month. Placeholder dates
 * (1900-01-01 from a spreadsheet) and blank dates used to produce a "1900-01" bar and a
 * label-less bar that dwarfed the real months.
 */
class ProjectFinanceChartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('projects.view', 'web');
    }

    private function actor(): User
    {
        $u = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => uniqid().'@test.local', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->givePermissionTo('projects.view');

        return $u;
    }

    private function project(): Project
    {
        return Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
    }

    private function rows(array $query = []): array
    {
        return $this->actingAs($this->actor())->getJson('/api/project-finance/reports/chart?'.http_build_query($query))->assertOk()->json('data');
    }

    public function test_months_are_ordered_and_amounts_are_placed_in_their_real_month(): void
    {
        $project = $this->project();
        ProjectBudget::create(['project_id' => $project->id, 'month' => '2025-12-01', 'category' => 'A', 'budgeted_cost' => 500]);
        ProjectExpense::create(['project_id' => $project->id, 'expense_date' => '2025-11-10', 'amount' => 200]);
        ProjectSubcontractorClaim::create(['project_id' => $project->id, 'submitted_date' => '2025-12-03', 'amount' => 90, 'certified_amount' => 80]);

        $rows = $this->rows();

        $this->assertSame(['2025-11', '2025-12'], array_column($rows, 'month'));
        $this->assertEquals(200, $rows[0]['actual_cost']);
        $this->assertEquals(500, $rows[1]['budgeted_cost']);
        $this->assertEquals(80, $rows[1]['certified_claims']);
    }

    public function test_a_claim_without_a_submitted_date_falls_back_to_its_certified_then_paid_date(): void
    {
        $project = $this->project();
        ProjectSubcontractorClaim::create(['project_id' => $project->id, 'submitted_date' => null, 'certified_date' => '2025-11-20', 'amount' => 10, 'certified_amount' => 10]);
        ProjectSubcontractorClaim::create(['project_id' => $project->id, 'submitted_date' => null, 'certified_date' => null, 'paid_date' => '2025-12-02', 'amount' => 20, 'certified_amount' => 20]);

        $rows = $this->rows();

        $this->assertSame(['2025-11', '2025-12'], array_column($rows, 'month'));
        $this->assertEquals(10, $rows[0]['certified_claims']);
        $this->assertEquals(20, $rows[1]['certified_claims']);
        $this->assertArrayNotHasKey('undated', end($rows));
    }

    public function test_placeholder_1900_dates_never_become_a_month(): void
    {
        $project = $this->project();
        ProjectSubcontractorClaim::create(['project_id' => $project->id, 'submitted_date' => '1900-01-02', 'certified_date' => '2025-12-05', 'amount' => 5, 'certified_amount' => 5]);
        ProjectExpense::create(['project_id' => $project->id, 'expense_date' => '1900-01-01', 'amount' => 7]);

        $rows = $this->rows();

        $this->assertNotContains('1900-01', array_column($rows, 'month'));
        $this->assertSame('2025-12', $rows[0]['month']);
        $this->assertEquals(5, $rows[0]['certified_claims']);
    }

    public function test_amounts_with_no_usable_date_are_reported_as_one_explicit_last_row(): void
    {
        $project = $this->project();
        ProjectExpense::create(['project_id' => $project->id, 'expense_date' => '2025-11-10', 'amount' => 200]);
        ProjectExpense::create(['project_id' => $project->id, 'expense_date' => '1900-01-01', 'amount' => 7]);
        ProjectSubcontractorClaim::create(['project_id' => $project->id, 'amount' => 1000, 'certified_amount' => 900]);

        $rows = $this->rows();
        $last = end($rows);

        $this->assertCount(2, $rows);
        $this->assertSame('2025-11', $rows[0]['month']);
        $this->assertTrue($last['undated']);
        $this->assertSame('No date', $last['month']);
        $this->assertEquals(7, $last['actual_cost']);
        $this->assertEquals(900, $last['certified_claims']);
    }

    public function test_the_undated_row_is_left_out_when_a_month_filter_is_applied(): void
    {
        $project = $this->project();
        ProjectExpense::create(['project_id' => $project->id, 'expense_date' => '2025-11-10', 'amount' => 200]);
        ProjectSubcontractorClaim::create(['project_id' => $project->id, 'amount' => 1000, 'certified_amount' => 900]);

        $rows = $this->rows(['month' => '2025-11']);

        $this->assertSame(['2025-11'], array_column($rows, 'month'));
        $this->assertEquals(0, $rows[0]['certified_claims']);
    }
}
