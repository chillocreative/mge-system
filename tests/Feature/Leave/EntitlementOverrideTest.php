<?php

namespace Tests\Feature\Leave;

use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\StaffLeaveEntitlementOverride;
use App\Services\Leave\EntitlementResolver;
use App\Services\Leave\LeavePolicy;
use Database\Seeders\LeavePolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntitlementOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LeavePolicySeeder::class);
    }

    private function resolver(): EntitlementResolver
    {
        return new EntitlementResolver(new LeavePolicy);
    }

    private function employee(string $hire = '2015-01-01', array $extra = []): Employee
    {
        return Employee::create(array_merge([
            'employee_no' => 'E-'.uniqid(), 'first_name' => 'T', 'category' => 'office', 'hire_date' => $hire,
        ], $extra));
    }

    private function al(): LeaveType
    {
        return LeaveType::where('code', 'AL')->sole();
    }

    public function test_an_override_replaces_the_tier(): void
    {
        $e = $this->employee(); // top tier would be 18
        StaffLeaveEntitlementOverride::create([
            'employee_id' => $e->id, 'leave_type_id' => $this->al()->id, 'year' => 2026, 'days' => 25,
        ]);

        $this->assertSame(25.0, $this->resolver()->entitlementFor($e, $this->al(), 2026));
    }

    public function test_a_year_specific_override_beats_a_standing_one(): void
    {
        $e = $this->employee();
        StaffLeaveEntitlementOverride::create(['employee_id' => $e->id, 'leave_type_id' => $this->al()->id, 'year' => null, 'days' => 20]);
        StaffLeaveEntitlementOverride::create(['employee_id' => $e->id, 'leave_type_id' => $this->al()->id, 'year' => 2026, 'days' => 30]);

        $this->assertSame(30.0, $this->resolver()->entitlementFor($e, $this->al(), 2026));
        // A year with no specific row falls back to the standing override.
        $this->assertSame(20.0, $this->resolver()->entitlementFor($e, $this->al(), 2027));
    }

    public function test_an_override_is_still_prorated_for_a_mid_year_joiner(): void
    {
        // Joined 1 Jul -> six months. Override 24 -> 12 after monthly proration.
        $e = $this->employee('2026-07-01');
        StaffLeaveEntitlementOverride::create(['employee_id' => $e->id, 'leave_type_id' => $this->al()->id, 'year' => 2026, 'days' => 24]);

        $this->assertSame(12.0, $this->resolver()->entitlementFor($e, $this->al(), 2026));
    }
}
