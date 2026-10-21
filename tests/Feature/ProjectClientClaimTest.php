<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectClientClaim;
use App\Models\ProjectSubcontractorClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Client Claims: what the company claims from the client (income), recorded per project
 * against a client picked from the Clients module. Same fields as Subcontractor Claims.
 */
class ProjectClientClaimTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
    }

    private function user(array $permissions): User
    {
        $u = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => uniqid().'@test.local', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->givePermissionTo($permissions);

        return $u;
    }

    private function editor(): User
    {
        return $this->user(['projects.view', 'projects.edit']);
    }

    private function project(): Project
    {
        return Project::create(['name' => 'Bridge', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
    }

    private function client(string $name = 'Acme Berhad'): Client
    {
        return Client::create(['company_name' => $name, 'contact_person' => 'Contact', 'email' => uniqid().'@client.test', 'status' => 'active']);
    }

    public function test_client_claim_is_created_against_a_client_with_all_claim_fields(): void
    {
        $project = $this->project();
        $client = $this->client();

        $this->actingAs($this->editor())->postJson('/api/project-finance/client-claims', [
            'project_id' => $project->id, 'client_id' => $client->id, 'claim_no' => 'CC-01', 'invoice_no' => 'INV-9', 'do_no' => 'DO-3',
            'submitted_date' => '2026-10-01', 'certified_date' => '2026-10-10', 'paid_date' => '2026-10-20', 'amount' => 1500.50, 'certified_amount' => 1400,
        ])->assertCreated()
            ->assertJsonPath('data.client.company_name', 'Acme Berhad')
            ->assertJsonPath('data.project.name', 'Bridge')
            ->assertJsonPath('data.claim_no', 'CC-01')
            ->assertJsonPath('data.amount', '1500.50')
            ->assertJsonPath('data.certified_amount', '1400.00')
            ->assertJsonPath('data.payment_timing', 'Early by 11 days');

        $this->assertDatabaseHas('project_client_claims', ['client_id' => $client->id, 'claim_no' => 'CC-01', 'status' => 'submitted', 'created_by' => User::latest('id')->value('id')]);
        // It is a separate record type: nothing lands in subcontractor claims.
        $this->assertDatabaseCount('project_subcontractor_claims', 0);
    }

    public function test_client_is_required_and_must_exist(): void
    {
        $editor = $this->editor();
        $project = $this->project();

        $this->actingAs($editor)->postJson('/api/project-finance/client-claims', ['project_id' => $project->id, 'amount' => 10])
            ->assertUnprocessable()->assertJsonValidationErrors('client_id');

        $this->actingAs($editor)->postJson('/api/project-finance/client-claims', ['project_id' => $project->id, 'client_id' => 9999, 'amount' => 10])
            ->assertUnprocessable()->assertJsonValidationErrors('client_id');

        $this->assertDatabaseCount('project_client_claims', 0);
    }

    public function test_viewing_needs_projects_view_and_changing_needs_projects_edit(): void
    {
        $project = $this->project();
        $client = $this->client();
        $claim = ProjectClientClaim::create(['project_id' => $project->id, 'client_id' => $client->id, 'amount' => 10]);
        $viewer = $this->user(['projects.view']);
        $nobody = $this->user([]);
        $payload = ['project_id' => $project->id, 'client_id' => $client->id, 'amount' => 5];

        $this->actingAs($viewer)->getJson('/api/project-finance/client-claims')->assertOk()->assertJsonCount(1, 'data.data');
        $this->actingAs($viewer)->postJson('/api/project-finance/client-claims', $payload)->assertForbidden();
        $this->actingAs($viewer)->putJson("/api/project-finance/client-claims/{$claim->id}", ['amount' => 99])->assertForbidden();
        $this->actingAs($viewer)->deleteJson("/api/project-finance/client-claims/{$claim->id}")->assertForbidden();
        $this->actingAs($nobody)->getJson('/api/project-finance/client-claims')->assertForbidden();

        $this->assertDatabaseHas('project_client_claims', ['id' => $claim->id, 'amount' => 10]);
    }

    public function test_client_claim_can_be_updated_and_deleted(): void
    {
        $editor = $this->editor();
        $project = $this->project();
        $acme = $this->client('Acme Berhad');
        $other = $this->client('Beta Sdn Bhd');
        $claim = ProjectClientClaim::create(['project_id' => $project->id, 'client_id' => $acme->id, 'amount' => 100]);

        $this->actingAs($editor)->putJson("/api/project-finance/client-claims/{$claim->id}", ['client_id' => $other->id, 'certified_amount' => 80, 'certified_date' => '2026-11-02'])
            ->assertOk()->assertJsonPath('data.client.company_name', 'Beta Sdn Bhd')->assertJsonPath('data.certified_amount', '80.00');

        $this->actingAs($editor)->deleteJson("/api/project-finance/client-claims/{$claim->id}")->assertOk();
        $this->assertDatabaseCount('project_client_claims', 0);
    }

    public function test_list_filters_by_project_month_and_client_name_and_keeps_deleted_clients_visible(): void
    {
        $p1 = $this->project();
        $p2 = $this->project();
        $acme = $this->client('Acme Berhad');
        $beta = $this->client('Beta Sdn Bhd');
        ProjectClientClaim::create(['project_id' => $p1->id, 'client_id' => $acme->id, 'submitted_date' => '2026-10-05', 'amount' => 1]);
        ProjectClientClaim::create(['project_id' => $p1->id, 'client_id' => $beta->id, 'submitted_date' => '2026-11-05', 'amount' => 2]);
        ProjectClientClaim::create(['project_id' => $p2->id, 'client_id' => $acme->id, 'submitted_date' => '2026-10-07', 'amount' => 3]);
        $editor = $this->editor();

        $amounts = fn (string $query) => collect($this->actingAs($editor)->getJson('/api/project-finance/client-claims?'.$query)->assertOk()->json('data.data'))->pluck('amount')->sort()->values()->all();

        $this->assertSame(['1.00', '2.00', '3.00'], $amounts(''));
        $this->assertSame(['1.00', '2.00'], $amounts('project_id='.$p1->id));
        $this->assertSame(['1.00', '3.00'], $amounts('month=2026-10'));
        $this->assertSame(['1.00', '3.00'], $amounts('vendor=acme'));
        $this->assertSame(['2.00'], $amounts('vendor=Beta&project_id='.$p1->id));

        $beta->delete();
        $names = collect($this->actingAs($editor)->getJson('/api/project-finance/client-claims?vendor=Beta')->assertOk()->json('data.data'))->pluck('client.company_name')->all();
        $this->assertSame(['Beta Sdn Bhd'], $names);
    }

    public function test_reports_chart_shows_client_claims_as_their_own_series_by_certified_amount(): void
    {
        $editor = $this->editor();
        $project = $this->project();
        $acme = $this->client('Acme Berhad');
        $beta = $this->client('Beta Sdn Bhd');
        ProjectClientClaim::create(['project_id' => $project->id, 'client_id' => $acme->id, 'submitted_date' => '2026-10-05', 'amount' => 1000, 'certified_amount' => 900]);
        ProjectClientClaim::create(['project_id' => $project->id, 'client_id' => $beta->id, 'submitted_date' => '2026-10-20', 'amount' => 500, 'certified_amount' => 400]);
        // Submitted but not certified yet: no income until it is certified.
        ProjectClientClaim::create(['project_id' => $project->id, 'client_id' => $acme->id, 'submitted_date' => '2026-11-03', 'amount' => 700]);
        // The cost-side series must stay where it was.
        ProjectSubcontractorClaim::create(['project_id' => $project->id, 'submitted_date' => '2026-10-09', 'amount' => 60, 'certified_amount' => 50]);

        $rows = $this->actingAs($editor)->getJson('/api/project-finance/reports/chart')->assertOk()->json('data');

        $this->assertSame(['2026-10', '2026-11'], array_column($rows, 'month'));
        $this->assertEquals(1300, $rows[0]['client_claims']);
        $this->assertEquals(50, $rows[0]['certified_claims']);
        $this->assertEquals(0, $rows[1]['client_claims']);

        $narrowed = $this->actingAs($editor)->getJson('/api/project-finance/reports/chart?vendor=Beta')->assertOk()->json('data');
        $this->assertEquals(400, $narrowed[0]['client_claims']);

        $this->assertEquals(900, $this->actingAs($editor)->getJson('/api/project-finance/reports/chart?project_id='.$project->id.'&month=2026-10&vendor=Acme')->json('data.0.client_claims'));
    }

    public function test_client_claim_with_no_valid_date_is_reported_as_undated_not_dropped(): void
    {
        $project = $this->project();
        ProjectClientClaim::create(['project_id' => $project->id, 'client_id' => $this->client()->id, 'submitted_date' => '1900-01-01', 'amount' => 300, 'certified_amount' => 250]);

        $rows = $this->actingAs($this->editor())->getJson('/api/project-finance/reports/chart')->assertOk()->json('data');

        $last = end($rows);
        $this->assertTrue($last['undated']);
        $this->assertEquals(250, $last['client_claims']);
    }

    public function test_excel_export_lists_project_and_client_names(): void
    {
        $project = $this->project();
        ProjectClientClaim::create(['project_id' => $project->id, 'client_id' => $this->client('Acme Berhad')->id, 'claim_no' => 'CC-7', 'submitted_date' => '2026-10-05', 'amount' => 1200, 'certified_amount' => 1100]);

        $response = $this->actingAs($this->editor())->get('/api/project-finance/client-claims/export');
        $response->assertOk();

        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet()->toArray();

        $this->assertSame(['id', 'project', 'client', 'claim_no', 'invoice_no', 'do_no', 'submitted_date', 'certified_date', 'paid_date', 'amount', 'certified_amount', 'status', 'notes'], $sheet[0]);
        $this->assertSame('Bridge', $sheet[1][1]);
        $this->assertSame('Acme Berhad', $sheet[1][2]);
        $this->assertSame('CC-7', $sheet[1][3]);
        $this->assertSame('2026-10-05', $sheet[1][6]);
    }

    public function test_pdf_export_and_report_exports_render(): void
    {
        $project = $this->project();
        ProjectClientClaim::create(['project_id' => $project->id, 'client_id' => $this->client()->id, 'submitted_date' => '2026-10-05', 'amount' => 1200, 'certified_amount' => 1100]);
        $editor = $this->editor();

        $pdf = $this->actingAs($editor)->get('/api/project-finance/client-claims/export?format=pdf');
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $report = $this->actingAs($editor)->get('/api/project-finance/reports/export');
        $report->assertOk();
        $sheet = IOFactory::load($report->baseResponse->getFile()->getPathname())->getActiveSheet()->toArray();
        $this->assertSame(['Month', 'Budgeted Cost', 'Actual Cost', 'Subcontractor Claims (Certified)', 'Client Claims (Certified)'], $sheet[0]);
        $this->assertEquals(1100, $sheet[1][4]);

        $reportPdf = $this->actingAs($editor)->get('/api/project-finance/reports/export?format=pdf');
        $reportPdf->assertOk();
        $this->assertStringStartsWith('%PDF', $reportPdf->getContent());
    }
}
