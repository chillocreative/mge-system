<?php

namespace Tests\Feature\Assets;

use App\Models\Project;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleProjectAssignment;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ciri 24.x — Import OLAK machinery & vehicles from official register (dry run by default).
 */
class OlakEquipmentImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * dry run writes nothing — Vehicle::count() === 0 after it
     */
    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('assets:import-olak')
            ->assertExitCode(0);

        $this->assertSame(0, Vehicle::count());
    }

    /**
     * @test
     * --commit creates exactly 46 vehicles: 26 machinery-sheet rows + 20 kenderaan rows
     */
    public function test_commit_creates_exactly_46_vehicles(): void
    {
        $this->artisan('assets:import-olak', ['--commit' => true])
            ->assertExitCode(0);

        $this->assertSame(46, Vehicle::count());
    }

    /**
     * @test
     * spot check of machinery row BPJ 4284 → make VOLVO, model EC210D, serial_no EX215,
     * year 2017, type machinery, custom_type Excavator, current_value 429300.00,
     * purchase_date 2017-05-12, status active
     */
    public function test_spot_check_machinery_row_bpj_4284(): void
    {
        $this->artisan('assets:import-olak', ['--commit' => true])
            ->assertExitCode(0);

        $vehicle = Vehicle::where('registration_no', 'BPJ 4284')->sole();

        $this->assertSame('VOLVO', $vehicle->make);
        $this->assertSame('EC210D', $vehicle->model);
        $this->assertSame('EX215', $vehicle->serial_no);
        $this->assertSame(2017, $vehicle->year);
        $this->assertSame('machinery', $vehicle->type);
        $this->assertSame('Excavator', $vehicle->custom_type);
        $this->assertEquals(429300.00, $vehicle->current_value);
        $this->assertEquals('2017-05-12', $vehicle->purchase_date?->format('Y-m-d'));
        $this->assertSame('active', $vehicle->status);
    }

    /**
     * @test
     * the 8 unplate'd machines get FL07, FL08, TBM-1, TBM-2, JG-1, JG-2, DR-1, DR-2
     * and their notes contain "No plate in source"
     */
    public function test_unplated_machines_get_serial_as_key(): void
    {
        $this->artisan('assets:import-olak', ['--commit' => true])
            ->assertExitCode(0);

        $keys = ['FL07', 'FL08', 'TBM-1', 'TBM-2', 'JG-1', 'JG-2', 'DR-1', 'DR-2'];

        foreach ($keys as $key) {
            $vehicle = Vehicle::where('registration_no', $key)->sole();
            $this->assertStringContainsString('No plate in source', $vehicle->notes);
        }
    }

    /**
     * @test
     * all motorcycles are type other + custom_type Motorcycle, all cars are type car
     * and their make is the brand (HONDA, not HONDA CRV 2.4 L)
     */
    public function test_motorcycles_and_cars_mapped_correctly(): void
    {
        $this->artisan('assets:import-olak', ['--commit' => true])
            ->assertExitCode(0);

        // Cars should be type='car'
        $cars = Vehicle::where('type', 'car')->get();
        $this->assertCount(12, $cars);
        foreach ($cars as $car) {
            $this->assertNull($car->custom_type);
            // Check that make is brand only, not combined with model
            $this->assertNotFalse(strpos('FORD HONDA MITSUBISHI NISSAN TOYOTA PERODUA VOLVO', $car->make));
        }

        // Motorcycles should be type='other' + custom_type='Motorcycle'
        $motorcycles = Vehicle::where('type', 'other')
            ->where('custom_type', 'Motorcycle')
            ->get();
        $this->assertCount(8, $motorcycles);
    }

    /**
     * @test
     * running --commit twice leaves Vehicle::count() === 46 and one VehicleDocument per expected doc
     */
    public function test_idempotent_on_second_commit(): void
    {
        $this->artisan('assets:import-olak', ['--commit' => true])
            ->assertExitCode(0);

        $firstCount = Vehicle::count();
        $firstDocCount = VehicleDocument::count();

        $this->artisan('assets:import-olak', ['--commit' => true])
            ->assertExitCode(0);

        $secondCount = Vehicle::count();
        $secondDocCount = VehicleDocument::count();

        $this->assertSame($firstCount, $secondCount);
        $this->assertSame(46, $secondCount);
        $this->assertSame($firstDocCount, $secondDocCount);
    }

    /**
     * @test
     * legacy retirement: create 2 rows with notes = 'Imported from Equipment & Machinery list',
     * run --commit, assert they are inactive and their notes unchanged; create a row with
     * registration_no = 'MGE 1234' and a different note and assert its status is untouched
     */
    public function test_legacy_placeholder_rows_are_retired(): void
    {
        Vehicle::create([
            'registration_no' => 'LEG-001',
            'make' => 'Old',
            'model' => 'Machine',
            'type' => 'machinery',
            'status' => 'active',
            'notes' => 'Imported from Equipment & Machinery list',
        ]);

        Vehicle::create([
            'registration_no' => 'LEG-002',
            'make' => 'Old',
            'model' => 'Truck',
            'type' => 'truck',
            'status' => 'active',
            'notes' => 'Imported from Equipment & Machinery list',
        ]);

        // Real Selangor-style plate with different note — should NOT be retired
        Vehicle::create([
            'registration_no' => 'MGE 1234',
            'make' => 'Volvo',
            'model' => 'EC210',
            'type' => 'machinery',
            'status' => 'active',
            'notes' => 'Some other import',
        ]);

        $this->artisan('assets:import-olak', ['--commit' => true])
            ->assertExitCode(0);

        $leg1 = Vehicle::where('registration_no', 'LEG-001')->sole();
        $leg2 = Vehicle::where('registration_no', 'LEG-002')->sole();
        $mgePlate = Vehicle::where('registration_no', 'MGE 1234')->sole();

        $this->assertSame('inactive', $leg1->status);
        $this->assertSame('Imported from Equipment & Machinery list', $leg1->notes);

        $this->assertSame('inactive', $leg2->status);
        $this->assertSame('Imported from Equipment & Machinery list', $leg2->notes);

        $this->assertSame('active', $mgePlate->status);
        $this->assertSame('Some other import', $mgePlate->notes);
    }

    /**
     * @test
     * assigned_to is null on every imported row
     */
    public function test_assigned_to_is_null_on_all_imported_rows(): void
    {
        $this->artisan('assets:import-olak', ['--commit' => true])
            ->assertExitCode(0);

        $all = Vehicle::all();
        foreach ($all as $v) {
            $this->assertNull($v->assigned_to);
        }
    }

    /**
     * @test
     * documents: a vehicle that already has a road_tax document does not get a second one
     */
    public function test_existing_road_tax_document_not_duplicated(): void
    {
        $actor = User::create([
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'u-test-olak@doc.com',
            'password' => bcrypt('x'),
        ]);

        Vehicle::create([
            'registration_no' => 'WB160J',
            'make' => 'FORD',
            'model' => 'RANGER',
            'type' => 'car',
            'status' => 'active',
            'notes' => 'Existing row',
            'created_by' => $actor->id,
        ]);

        $wb160j = Vehicle::where('registration_no', 'WB160J')->sole();

        VehicleDocument::create([
            'vehicle_id' => $wb160j->id,
            'doc_type' => 'road_tax',
            'expiry_date' => '2026-01-01',
            'notes' => 'Existing doc',
        ]);

        $this->artisan('assets:import-olak', ['--commit' => true])
            ->assertExitCode(0);

        $docs = VehicleDocument::where('vehicle_id', $wb160j->id)
            ->where('doc_type', 'road_tax')
            ->count();
        $this->assertSame(1, $docs);
    }

    /**
     * @test
     * project: one project whose name contains OLAK → 46 open assignments;
     * two matching projects → zero assignments and exit code 0;
     * no matching project → zero assignments and exit code 0
     */
    public function test_project_assignment_with_one_olak_project(): void
    {
        $actor = User::create([
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'u-test-olk@proj.com',
            'password' => bcrypt('x'),
        ]);

        Project::create(['name' => 'OLAK Site Maintenance', 'code' => 'OLK', 'status' => 'in_progress']);

        $this->artisan('assets:import-olak', ['--commit' => true])
            ->assertExitCode(0);

        $openAssignments = VehicleProjectAssignment::whereNull('released_at')->count();
        $this->assertSame(46, $openAssignments);
    }

    public function test_project_assignment_with_multiple_matching_projects(): void
    {
        $actor = User::create([
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'u-test-mul@proj.com',
            'password' => bcrypt('x'),
        ]);

        Project::create(['name' => 'OLAK Site A', 'code' => 'OLK', 'status' => 'in_progress']);
        Project::create(['name' => 'OLAK Site B', 'code' => 'OLB', 'status' => 'in_progress']);

        $this->artisan('assets:import-olak', ['--commit' => true])
            ->assertExitCode(0);

        $openAssignments = VehicleProjectAssignment::whereNull('released_at')->count();
        $this->assertSame(0, $openAssignments);
    }

    public function test_project_assignment_with_no_matching_project(): void
    {
        $actor = User::create([
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'u-test-nom@proj.com',
            'password' => bcrypt('x'),
        ]);

        Project::create(['name' => 'Bukit Talam', 'code' => 'OTH', 'status' => 'in_progress']);

        $this->artisan('assets:import-olak', ['--commit' => true])
            ->assertExitCode(0);

        $openAssignments = VehicleProjectAssignment::whereNull('released_at')->count();
        $this->assertSame(0, $openAssignments);
    }

    /**
     * @test
     * dry run prints the full plan — summary says Creates 46 but Vehicle::count() === 0 (nothing written)
     */
    public function test_dry_run_prints_the_full_plan(): void
    {
        $this->withoutMockingConsoleOutput();

        $buffer = new \Symfony\Component\Console\Output\BufferedOutput;
        $this->app[Kernel::class]->call('assets:import-olak', [], $buffer);

        $output = $buffer->fetch();

        // Assert it contains exactly 46 CREATE lines
        $lines = explode("\n", $output);
        $createLines = preg_grep('/^CREATE\s+/', $lines);
        $this->assertCount(46, $createLines, 'Should have exactly 46 CREATE lines');

        // Assert one of them is for BPJ 4284
        $this->assertStringContainsString('CREATE  BPJ 4284', $output);

        // Assert the specific DOC line exists
        $this->assertStringContainsString('DOC     WB160J     road_tax expiry 2027-03-16', $output);

        // Assert the summary line mentions Creates 46
        $this->assertMatchesRegularExpression('/Creates\s+46/', $output);

        // Verify that nothing was actually written
        $this->assertSame(0, Vehicle::count());
    }

    /**
     * @test
     * after a commit, a subsequent dry run should show Updates instead of Creates
     */
    public function test_dry_run_after_a_commit_reports_updates(): void
    {
        $this->withoutMockingConsoleOutput();

        // First commit to create all vehicles
        $commitBuffer = new \Symfony\Component\Console\Output\BufferedOutput;
        $this->app[Kernel::class]->call('assets:import-olak', ['--commit' => true], $commitBuffer);

        // Reset buffer for second run
        $buffer = new \Symfony\Component\Console\Output\BufferedOutput;
        $this->app[Kernel::class]->call('assets:import-olak', [], $buffer);

        $output = $buffer->fetch();

        // After commit, dry run should show 46 UPDATE lines and 0 Creates
        $lines = explode("\n", $output);
        $updateLines = preg_grep('/^UPDATE\s+/', $lines);
        $this->assertCount(46, $updateLines, 'Should have exactly 46 UPDATE lines after commit');

        $createLines = preg_grep('/^CREATE\s+/', $lines);
        $this->assertCount(0, $createLines, 'Should have zero CREATE lines after commit');

        // Assert the summary says Creates 0
        $this->assertMatchesRegularExpression('/Creates\s+0/', $output);
    }

    /**
     * @test
     * an unrelated vehicle with notes not containing self::SOURCE is not assigned a project
     */
    public function test_unrelated_vehicle_is_not_assigned(): void
    {
        $actor = User::create([
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'u-test-unrel@proj.com',
            'password' => bcrypt('x'),
        ]);

        Project::create(['name' => 'OLAK Site Maintenance', 'code' => 'OLK', 'status' => 'in_progress']);

        // Create an unrelated vehicle
        Vehicle::create([
            'registration_no' => 'ABC 111',
            'make' => 'TOYOTA',
            'model' => 'HILUX',
            'type' => 'car',
            'status' => 'active',
            'notes' => 'Some other data',
            'created_by' => $actor->id,
        ]);

        $this->artisan('assets:import-olak', ['--commit' => true])
            ->assertExitCode(0);

        // Should have exactly 46 assignments (only imported vehicles)
        $assignmentCount = VehicleProjectAssignment::whereNull('released_at')->count();
        $this->assertSame(46, $assignmentCount);

        // ABC 111 should have zero assignment rows
        $abcAssignments = VehicleProjectAssignment::where('vehicle_id',
            Vehicle::where('registration_no', 'ABC 111')->sole()->id)->count();
        $this->assertSame(0, $abcAssignments);
    }

    /**
     * @test
     * dry run reports the assignment plan: empty database, one OLAK project,
     * output contains exactly 46 ASSIGN lines and summary shows Assigns 46
     */
    public function test_dry_run_reports_the_assignment_plan(): void
    {
        $this->withoutMockingConsoleOutput();

        // Setup: one OLAK project
        Project::create(['name' => 'OLAK Site Maintenance', 'code' => 'OLK', 'status' => 'in_progress']);

        $buffer = new \Symfony\Component\Console\Output\BufferedOutput;
        $this->app[Kernel::class]->call('assets:import-olak', [], $buffer);

        $output = $buffer->fetch();

        // Count ASSIGN lines (must be exactly 46)
        $lines = explode("\n", $output);
        $assignLines = preg_grep('/^ASSIGN\s+.*\s->\s/', $lines);
        $this->assertCount(46, $assignLines, 'Should have exactly 46 ASSIGN lines');

        // Assert summary mentions Assigns 46
        $this->assertMatchesRegularExpression('/Assigns\s+46/', $output);

        // Verify that no assignments were written
        $this->assertSame(0, VehicleProjectAssignment::count());
    }

    /**
     * @test
     * after a commit, a subsequent dry run shows Assigns 0 and mentions 46 already assigned
     */
    public function test_dry_run_after_commit_reports_already_assigned(): void
    {
        $this->withoutMockingConsoleOutput();

        // Create OLAK project first
        Project::create(['name' => 'OLAK Site Maintenance', 'code' => 'OLK', 'status' => 'in_progress']);

        // First commit to create all vehicles and their assignments
        $commitBuffer = new \Symfony\Component\Console\Output\BufferedOutput;
        $this->app[Kernel::class]->call('assets:import-olak', ['--commit' => true], $commitBuffer);

        // Run dry run after commit
        $buffer = new \Symfony\Component\Console\Output\BufferedOutput;
        $this->app[Kernel::class]->call('assets:import-olak', [], $buffer);

        $output = $buffer->fetch();

        // Should show Assigns 0
        $this->assertMatchesRegularExpression('/Assigns\s+0/', $output);

        // Should mention already assigned
        $this->assertStringContainsString('already assigned', $output);

        // Verify assignments count is still 46 (not doubled)
        $this->assertSame(46, VehicleProjectAssignment::whereNull('released_at')->count());
    }

    /**
     * @test
     * conflict row is reported and not assigned: move one vehicle's assignment,
     * dry run shows CONFLICT line, Assigns 0 new, no growth in open assignments
     */
    public function test_conflict_row_is_reported_not_assigned(): void
    {
        $this->withoutMockingConsoleOutput();

        // Create OLAK project first
        Project::create(['name' => 'OLAK Site Maintenance', 'code' => 'OLK', 'status' => 'in_progress']);

        // Commit to create vehicles and assignments
        $commitBuffer = new \Symfony\Component\Console\Output\BufferedOutput;
        $this->app[Kernel::class]->call('assets:import-olak', ['--commit' => true], $commitBuffer);

        // Create a second non-OLAK project
        $secondProject = Project::create([
            'name' => 'Bukit Talam Roadworks',
            'code' => 'BTR',
            'status' => 'in_progress',
        ]);

        // Move one vehicle's open assignment to the second project
        $firstAssignment = VehicleProjectAssignment::whereNull('released_at')->first();
        $this->assertNotNull($firstAssignment, 'Should have at least one assignment');

        $firstAssignment->update(['project_id' => $secondProject->id]);
        $conflictPlate = Vehicle::findOrFail($firstAssignment->vehicle_id)->registration_no;

        $openBefore = VehicleProjectAssignment::whereNull('released_at')->count();

        // Run dry run
        $buffer = new \Symfony\Component\Console\Output\BufferedOutput;
        $this->app[Kernel::class]->call('assets:import-olak', [], $buffer);

        $output = $buffer->fetch();

        // The conflicting plate is named on a CONFLICT line and never on an ASSIGN line
        $this->assertStringContainsString("CONFLICT {$conflictPlate}:", $output);
        $this->assertStringNotContainsString("ASSIGN  {$conflictPlate} ->", $output);

        // Nothing would be newly assigned: 45 are already on OLAK, 1 conflicts
        $this->assertMatchesRegularExpression('/Assigns\s+0\b/', $output);
        $this->assertStringContainsString('1 conflict', $output);

        // Verify open assignments did not grow
        $this->assertSame(
            $openBefore,
            VehicleProjectAssignment::whereNull('released_at')->count(),
            'A dry run must not create an assignment'
        );
    }

    /**
     * @test
     * two matching projects → ASSIGN skipped: 2, zero ASSIGN lines, zero assignments created
     */
    public function test_two_olak_projects_results_in_skipped_assignment(): void
    {
        $this->withoutMockingConsoleOutput();

        // Create two matching OLAK projects
        Project::create(['name' => 'OLAK Site A', 'code' => 'OLA', 'status' => 'in_progress']);
        Project::create(['name' => 'OLAK Site B', 'code' => 'OLB', 'status' => 'in_progress']);

        $buffer = new \Symfony\Component\Console\Output\BufferedOutput;
        $this->app[Kernel::class]->call('assets:import-olak', ['--commit' => true], $buffer);

        $output = $buffer->fetch();

        // Should show skipped message
        $this->assertStringContainsString('ASSIGN skipped: 2', $output);

        // Should have zero ASSIGN lines
        $lines = explode("\n", $output);
        $assignLines = preg_grep('/^ASSIGN\s+.*\s->\s/', $lines);
        $this->assertCount(0, $assignLines, 'Should have zero ASSIGN lines');

        // Should have zero assignments created
        $this->assertSame(0, VehicleProjectAssignment::whereNull('released_at')->count());
    }
}
