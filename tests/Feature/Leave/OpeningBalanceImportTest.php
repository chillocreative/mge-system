<?php

namespace Tests\Feature\Leave;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Services\Leave\LeaveEngine;
use Database\Seeders\LeavePolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpeningBalanceImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LeavePolicySeeder::class);
        config(['leave.engine_enabled' => true]);
    }

    private function csv(string $body): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ob').'.csv';
        file_put_contents($path, $body);

        return $path;
    }

    public function test_dry_run_writes_nothing(): void
    {
        $e = Employee::create(['employee_no' => 'OB1', 'first_name' => 'A', 'category' => 'office', 'hire_date' => '2015-01-01']);
        $file = $this->csv("employee_no,leave_code,days_taken\nOB1,AL,4\n");

        $this->artisan("leave:opening-balance {$file} --year=2026")
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame(0, LeaveBalance::count());
    }

    public function test_commit_records_taken_days_as_a_negative_adjustment(): void
    {
        $e = Employee::create(['employee_no' => 'OB2', 'first_name' => 'A', 'category' => 'office', 'hire_date' => '2015-01-01']);
        $file = $this->csv("employee_no,leave_code,days_taken\nOB2,AL,4\n");

        $this->artisan("leave:opening-balance {$file} --year=2026 --commit")->assertSuccessful();

        $al = LeaveType::where('code', 'AL')->sole();
        $balance = LeaveBalance::where('employee_id', $e->id)->where('leave_type_id', $al->id)->sole();
        $this->assertSame(-4.0, (float) $balance->adjustment_days);

        // Top tier 18, minus 4 already taken -> 14 available.
        $summary = app(LeaveEngine::class)->summary($e->fresh(), $al, 2026);
        $this->assertSame(14.0, (float) $summary['available']);
    }

    public function test_it_is_idempotent_and_overwrites_rather_than_stacks(): void
    {
        $e = Employee::create(['employee_no' => 'OB3', 'first_name' => 'A', 'category' => 'office', 'hire_date' => '2015-01-01']);
        $file = $this->csv("employee_no,leave_code,days_taken\nOB3,AL,4\n");

        $this->artisan("leave:opening-balance {$file} --year=2026 --commit")->assertSuccessful();
        $this->artisan("leave:opening-balance {$file} --year=2026 --commit")->assertSuccessful();

        $al = LeaveType::where('code', 'AL')->sole();
        $this->assertSame(-4.0, (float) LeaveBalance::where('employee_id', $e->id)->where('leave_type_id', $al->id)->sole()->adjustment_days);
    }

    public function test_it_refuses_the_whole_file_on_a_bad_row(): void
    {
        Employee::create(['employee_no' => 'OB4', 'first_name' => 'A', 'category' => 'office']);
        $file = $this->csv("employee_no,leave_code,days_taken\nOB4,AL,4\nNOPE,AL,2\n");

        $this->artisan("leave:opening-balance {$file} --year=2026 --commit")
            ->expectsOutputToContain('no employee with number')
            ->assertFailed();

        $this->assertSame(0, LeaveBalance::count());
    }
}
