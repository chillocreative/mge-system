<?php

namespace Tests\Feature\ReportData;

use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ContractParticularsTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->editor = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $this->editor->givePermissionTo(['projects.view', 'projects.edit']);
    }

    private function project(): Project
    {
        return Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
    }

    public function test_setting_a_contract_as_main_unsets_the_previous_one(): void
    {
        $project = $this->project();
        $c1 = ProjectContract::create(['project_id' => $project->id, 'title' => 'C1', 'is_main' => true]);
        $c2 = ProjectContract::create(['project_id' => $project->id, 'title' => 'C2']);

        $this->actingAs($this->editor)->putJson("/api/project-contracts/{$c2->id}", ['is_main' => true])->assertOk();

        $this->assertFalse($c1->fresh()->is_main);
        $this->assertTrue($c2->fresh()->is_main);
    }

    public function test_particulars_are_read_and_written_on_the_main_contract(): void
    {
        $project = $this->project();
        ProjectContract::create(['project_id' => $project->id, 'title' => 'Main', 'is_main' => true]);

        $this->actingAs($this->editor)->putJson("/api/projects/{$project->id}/contract-particulars", [
            'contract_sum' => 288000000,
            'performance_bond_amount' => 14400000,
            'duration_months' => 24,
            'dlp_months' => 12,
            'lad_per_day' => 52128,
            'possession_date' => '2025-10-31',
            'completion_date' => '2027-10-31',
            'cidb_registration' => 'TBA',
            'insurances' => [['type' => "Contractor's All Risk", 'insurer' => 'PACIFIC INSURANCE', 'policy_no' => 'CEC-E0039188-H1', 'period_from' => '2025-10-31', 'period_to' => '2027-10-31', 'maintenance_from' => '2027-11-01', 'maintenance_to' => '2029-02-12']],
        ])->assertOk();

        $res = $this->actingAs($this->editor)->getJson("/api/projects/{$project->id}/contract-particulars")->assertOk();
        $this->assertSame('Two Hundred Eighty-Eight Million Ringgit Only', $res->json('data.contract_sum_words'));
        $this->assertSame('PACIFIC INSURANCE', $res->json('data.contract.insurances.0.insurer'));
    }

    public function test_returns_404_when_project_has_no_main_contract(): void
    {
        $project = $this->project();

        $this->actingAs($this->editor)->getJson("/api/projects/{$project->id}/contract-particulars")->assertNotFound();
    }
}
