<?php

namespace Tests\Feature\Assets;

use App\Models\Project;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleProjectAssignment;
use App\Services\AssetUsageReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AssetUsageReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_report_calculates_unique_usage_days_and_due_statuses(): void
    {
        CarbonImmutable::setTestNow('2026-10-01');
        $vehicle = Vehicle::create([
            'registration_no' => 'MCH-001',
            'make' => 'Komatsu',
            'model' => 'PC200',
            'type' => 'machinery',
            'category' => 'machine',
            'status' => 'active',
        ]);
        $firstProject = $this->project('P1');
        $secondProject = $this->project('P2');

        VehicleProjectAssignment::create([
            'vehicle_id' => $vehicle->id,
            'project_id' => $firstProject->id,
            'assigned_at' => '2026-09-01',
            'released_at' => '2026-09-15',
        ]);
        VehicleProjectAssignment::create([
            'vehicle_id' => $vehicle->id,
            'project_id' => $secondProject->id,
            'assigned_at' => '2026-09-15',
            'released_at' => '2026-09-20',
        ]);
        $vehicle->maintenanceLogs()->create([
            'maintenance_type' => 'preventive',
            'performed_date' => '2026-09-01',
            'next_due_date' => '2026-09-30',
            'description' => 'Scheduled service',
            'status' => 'completed',
        ]);
        $vehicle->documents()->create([
            'doc_type' => 'road_tax',
            'expiry_date' => '2026-10-11',
        ]);

        $report = app(AssetUsageReportService::class)->generate('2026-09', 'machine');
        $row = $report['rows'][0];

        $this->assertSame(20, $row['monthly_usage_days']);
        $this->assertSame(20, $row['total_usage_days']);
        $this->assertSame(2, $row['monthly_assignment_count']);
        $this->assertSame('overdue', $row['maintenance_status']);
        $this->assertSame('expiring', $row['road_tax_status']);
        $this->assertSame(10, $row['road_tax_days_remaining']);
        $this->assertSame(1, $report['summary']['used_assets']);
    }

    public function test_authorized_user_can_view_and_export_all_formats(): void
    {
        Permission::findOrCreate('assets.view', 'web');
        $user = User::create([
            'first_name' => 'Asset',
            'last_name' => 'Viewer',
            'email' => 'asset-viewer-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->givePermissionTo('assets.view');
        Vehicle::create([
            'registration_no' => 'VEH-001',
            'make' => 'Toyota',
            'type' => 'car',
            'category' => 'vehicle',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->getJson('/api/assets/usage-report?month=2026-09')
            ->assertOk()
            ->assertJsonPath('data.summary.total_assets', 1);

        foreach (['xlsx', 'docx', 'pdf'] as $format) {
            $this->actingAs($user)
                ->get("/api/assets/usage-report/export?month=2026-09&format={$format}")
                ->assertOk()
                ->assertHeader('content-disposition');
        }
    }

    private function project(string $code): Project
    {
        return Project::create([
            'name' => 'Project '.$code,
            'code' => $code.uniqid(),
            'status' => 'in_progress',
        ]);
    }
}
