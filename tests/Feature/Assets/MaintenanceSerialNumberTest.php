<?php

namespace Tests\Feature\Assets;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\MaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceSerialNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_serial_number_is_snapshotted_on_new_maintenance_log(): void
    {
        $vehicle = Vehicle::create([
            'registration_no' => 'MCH-100',
            'make' => 'Komatsu',
            'type' => 'machinery',
            'serial_no' => 'SER-2026-001',
            'status' => 'active',
        ]);
        $user = User::create([
            'first_name' => 'Asset',
            'last_name' => 'Manager',
            'email' => 'asset-manager-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $log = app(MaintenanceService::class)->create([
            'maintainable_type' => Vehicle::class,
            'maintainable_id' => $vehicle->id,
            'maintenance_type' => 'preventive',
            'performed_date' => '2026-10-01',
            'description' => 'Routine service',
            'status' => 'completed',
        ], $user->id);

        $this->assertSame('SER-2026-001', $log->serial_no);
        $this->assertDatabaseHas('maintenance_logs', [
            'id' => $log->id,
            'serial_no' => 'SER-2026-001',
        ]);
    }

    public function test_existing_maintenance_logs_can_keep_a_null_serial_number(): void
    {
        $vehicle = Vehicle::create([
            'registration_no' => 'VEH-200',
            'make' => 'Toyota',
            'type' => 'car',
            'serial_no' => 'SER-OLD',
            'status' => 'active',
        ]);

        $log = MaintenanceLog::create([
            'maintainable_type' => Vehicle::class,
            'maintainable_id' => $vehicle->id,
            'serial_no' => null,
            'maintenance_type' => 'corrective',
            'performed_date' => '2026-09-01',
            'description' => 'Historical repair',
            'status' => 'completed',
        ]);

        $this->assertNull($log->fresh()->serial_no);
        $this->assertSame('SER-OLD', $log->fresh()->maintainable->serial_no);
    }
}
