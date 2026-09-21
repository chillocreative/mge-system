<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Covers the optional `month` filter on GET /api/project-invoices/summary:
 * it must restrict totals to invoices whose invoice_date falls in that
 * calendar month, leave the response shape unchanged, and reject a
 * malformed month value.
 */
class ProjectInvoiceSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('projects.view', 'web');
    }

    private function actor(): User
    {
        $u = User::create([
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'u-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('x'),
            'status' => 'active',
        ]);
        $u->givePermissionTo('projects.view');

        return $u;
    }

    private function project(): Project
    {
        return Project::create([
            'name' => 'P',
            'code' => 'PR'.random_int(1000, 9999),
            'status' => 'in_progress',
        ]);
    }

    private function invoice(Project $project, string $type, string $invoiceDate, float $amount): ProjectInvoice
    {
        return ProjectInvoice::create([
            'project_id' => $project->id,
            'type' => $type,
            'invoice_date' => $invoiceDate,
            'amount' => $amount,
            'status' => 'draft',
        ]);
    }

    public function test_month_filter_restricts_summary_to_that_month(): void
    {
        $actor = $this->actor();
        $project = $this->project();

        $this->invoice($project, 'client', '2026-01-10', 1000);
        $this->invoice($project, 'client', '2026-02-15', 500);
        $this->invoice($project, 'subcon', '2026-02-20', 200);

        $response = $this->actingAs($actor)->getJson('/api/project-invoices/summary?month=2026-02');

        $response->assertOk();
        $response->assertJsonPath('data.total_client', 500);
        $response->assertJsonPath('data.total_subcon', 200);
        $response->assertJsonPath('data.profit', 300);
        $response->assertJsonPath('data.client_count', 1);
        $response->assertJsonPath('data.subcon_count', 1);
    }

    public function test_without_month_returns_all_invoices(): void
    {
        $actor = $this->actor();
        $project = $this->project();

        $this->invoice($project, 'client', '2026-01-10', 1000);
        $this->invoice($project, 'client', '2026-02-15', 500);
        $this->invoice($project, 'subcon', '2026-02-20', 200);

        $response = $this->actingAs($actor)->getJson('/api/project-invoices/summary');

        $response->assertOk();
        $response->assertJsonPath('data.total_client', 1500);
        $response->assertJsonPath('data.total_subcon', 200);
        $response->assertJsonPath('data.client_count', 2);
        $response->assertJsonPath('data.subcon_count', 1);
    }

    public function test_invalid_month_format_is_rejected(): void
    {
        $actor = $this->actor();

        $response = $this->actingAs($actor)->getJson('/api/project-invoices/summary?month=bad');

        $response->assertStatus(422);
    }
}
