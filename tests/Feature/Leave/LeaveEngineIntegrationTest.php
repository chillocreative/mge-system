<?php

namespace Tests\Feature\Leave;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveDay;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PublicHoliday;
use App\Models\User;
use App\Services\Leave\LeaveEngine;
use App\Services\LeaveService;
use Database\Seeders\LeavePolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Phase E — the engine wired into LeaveService, behind config('leave.engine_enabled').
 *
 * The first test is the most important one in this file: with the flag off,
 * behaviour must be byte-for-byte what it is in production today. MGE-PMS is
 * live, and a deploy that quietly changed leave arithmetic would be discovered
 * by employees, not by us.
 */
class LeaveEngineIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LeavePolicySeeder::class);
        Storage::fake('local');
    }

    private function service(): LeaveService
    {
        return app(LeaveService::class);
    }

    private function employee(array $extra = []): Employee
    {
        return Employee::create(array_merge([
            'employee_no' => 'E-'.uniqid(),
            'first_name' => 'Test',
            'category' => 'office',
            'hire_date' => '2015-01-01', // top tier: 16 days AL
        ], $extra));
    }

    private function annual(): LeaveType
    {
        return LeaveType::where('code', 'AL')->sole();
    }

    private function actor(): User
    {
        return User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('secret'),
            // authorizeStage() accepts a system-wide manager/director approver.
            'is_manager' => true,
            'is_director' => true,
        ]);
    }

    // ── Flag off: production behaviour must not change ──────────────────────

    public function test_with_the_engine_off_days_are_raw_calendar_days_as_before(): void
    {
        config(['leave.engine_enabled' => false]);

        $employee = $this->employee();

        // Mon 7 Sep – Sun 13 Sep 2026: includes a weekend.
        $request = $this->service()->apply([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->annual()->id,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-13',
        ], $this->actor()->id);

        // Old arithmetic: diffInDays + 1, weekend included.
        $this->assertSame(7.0, (float) $request->days_count);
        $this->assertSame(0, LeaveDay::count(), 'The engine must not write leave_days while disabled.');
    }

    public function test_with_the_engine_off_a_request_beyond_the_balance_is_still_allowed(): void
    {
        config(['leave.engine_enabled' => false]);

        $employee = $this->employee();

        // 100 days of annual leave. Absurd, but this is current behaviour and
        // this test documents it rather than endorsing it.
        $request = $this->service()->apply([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->annual()->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-04-10',
        ], $this->actor()->id);

        $this->assertGreaterThan(90, (float) $request->days_count);
    }

    // ── Flag on ────────────────────────────────────────────────────────────

    public function test_with_the_engine_on_rest_days_and_holidays_are_excluded(): void
    {
        config(['leave.engine_enabled' => true]);
        PublicHoliday::create(['name' => 'Malaysia Day', 'date' => '2026-09-16', 'year' => 2026]);

        $employee = $this->employee();

        // Mon 14 – Sun 20 Sep 2026: 7 calendar days, minus Malaysia Day (Wed),
        // minus Sat + Sun = 4 deducted.
        $request = $this->service()->apply([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->annual()->id,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-20',
        ], $this->actor()->id);

        $this->assertSame(4.0, (float) $request->days_count);

        // Every calendar day is recorded, including the excluded ones with reasons.
        $this->assertSame(7, LeaveDay::where('leave_request_id', $request->id)->count());
        $this->assertSame(3, LeaveDay::where('leave_request_id', $request->id)->where('is_deducted', false)->count());
    }

    public function test_a_request_exceeding_the_balance_is_refused(): void
    {
        config(['leave.engine_enabled' => true]);

        $employee = $this->employee(['hire_date' => '2026-01-01']); // <2y tier: 8 days

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('only');

        // Far more working days than the 8-day entitlement.
        $this->service()->apply([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->annual()->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-04-30',
        ], $this->actor()->id);
    }

    public function test_a_second_pending_request_is_measured_against_the_first(): void
    {
        config(['leave.engine_enabled' => true]);

        $employee = $this->employee(['hire_date' => '2026-01-01']); // 8 days
        $actor = $this->actor();

        // 5 working days, leaving 3.
        $this->service()->apply([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->annual()->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-06',
        ], $actor->id);

        // Another 5 working days would need 5 but only 3 remain. Without pending
        // being counted, both would be approved and the employee would overdraw
        // (plan 8.4).
        $this->expectException(HttpException::class);
        $this->service()->apply([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->annual()->id,
            'start_date' => '2026-03-09',
            'end_date' => '2026-03-13',
        ], $actor->id);
    }

    public function test_a_required_supporting_document_is_enforced(): void
    {
        config(['leave.engine_enabled' => true]);

        // Hospitalisation leave is seeded with requires_attachment = true.
        $hospitalisation = LeaveType::where('code', 'HL')->sole();
        $employee = $this->employee();

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('supporting document');

        $this->service()->apply([
            'employee_id' => $employee->id,
            'leave_type_id' => $hospitalisation->id,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-15',
        ], $this->actor()->id);
    }

    public function test_a_request_with_the_required_document_is_accepted(): void
    {
        config(['leave.engine_enabled' => true]);

        $hospitalisation = LeaveType::where('code', 'HL')->sole();
        $employee = $this->employee();

        $request = $this->service()->apply([
            'employee_id' => $employee->id,
            'leave_type_id' => $hospitalisation->id,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-15',
        ], $this->actor()->id, UploadedFile::fake()->create('admission.pdf', 100));

        $this->assertNotNull($request->attachment_path);
        $this->assertSame(2.0, (float) $request->days_count);
    }

    public function test_a_cross_year_request_charges_each_year_separately(): void
    {
        config(['leave.engine_enabled' => true]);

        $employee = $this->employee(['category' => 'site']); // works Mon–Sat
        $actor = $this->actor();

        // Wed 30 Dec 2026 – Sat 2 Jan 2027.
        $request = $this->service()->apply([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->annual()->id,
            'start_date' => '2026-12-30',
            'end_date' => '2027-01-02',
        ], $actor->id);

        $this->service()->approve($request->id, $actor);

        $balances = LeaveBalance::where('employee_id', $employee->id)
            ->orderBy('year')->get()->keyBy('year');

        // Two separate balance rows, each carrying only its own share.
        $this->assertCount(2, $balances);
        $this->assertSame(2.0, (float) $balances[2026]->used_days);
        $this->assertSame(2.0, (float) $balances[2027]->used_days);
    }

    public function test_approving_stores_the_policy_snapshot_with_the_balance(): void
    {
        config(['leave.engine_enabled' => true]);

        $employee = $this->employee();
        $actor = $this->actor();

        $request = $this->service()->apply([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->annual()->id,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-15',
        ], $actor->id);

        $this->service()->approve($request->id, $actor);

        $balance = LeaveBalance::where('employee_id', $employee->id)->sole();

        // The balance records the policy it was calculated under, so a later
        // settings change cannot retroactively rewrite it (plan 7.3.3b).
        $this->assertIsArray($balance->rule_snapshot);
        $this->assertSame('next_year', $balance->rule_snapshot['tier_crossing']);
        $this->assertNotNull($balance->calculated_at);
    }

    public function test_cancelling_returns_the_days(): void
    {
        config(['leave.engine_enabled' => true]);

        $employee = $this->employee();
        $actor = $this->actor();

        $request = $this->service()->apply([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->annual()->id,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-18',
        ], $actor->id);

        $this->service()->approve($request->id, $actor);
        $this->assertSame(5.0, (float) LeaveBalance::where('employee_id', $employee->id)->sole()->used_days);

        $this->service()->cancel($request->id);

        $this->assertSame(0, LeaveDay::where('leave_request_id', $request->id)->count());
        $this->assertSame(0.0, (float) LeaveBalance::where('employee_id', $employee->id)->sole()->used_days);
    }

    public function test_rejecting_frees_the_pending_balance(): void
    {
        config(['leave.engine_enabled' => true]);

        $employee = $this->employee(['hire_date' => '2026-01-01']); // 8 days
        $actor = $this->actor();

        $first = $this->service()->apply([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->annual()->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-06',
        ], $actor->id);

        $this->service()->reject($first->id, $actor, 'Not approved.');

        // With the first request rejected, the days are available again.
        $second = $this->service()->apply([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->annual()->id,
            'start_date' => '2026-03-09',
            'end_date' => '2026-03-13',
        ], $actor->id);

        $this->assertSame(5.0, (float) $second->days_count);
    }

    public function test_negative_balances_can_be_permitted_by_policy(): void
    {
        config(['leave.engine_enabled' => true]);

        \App\Models\LeavePolicySetting::global()
            ->where('key', 'allow_negative_balance')
            ->update(['value' => '1']);

        $employee = $this->employee(['hire_date' => '2026-01-01']); // 8 days

        $request = $this->service()->apply([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->annual()->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-04-30',
        ], $this->actor()->id);

        $this->assertGreaterThan(8.0, (float) $request->days_count);
    }

    // ── Pool caps ──────────────────────────────────────────────────────────

    public function test_hospitalisation_shows_the_effective_balance_not_the_raw_cap(): void
    {
        config(['leave.engine_enabled' => true]);

        $employee = $this->employee();
        $actor = $this->actor();
        $sick = LeaveType::where('code', 'MC')->sole();

        // Take 4 working days of sick leave.
        $request = $this->service()->apply([
            'employee_id' => $employee->id,
            'leave_type_id' => $sick->id,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-17',
        ], $actor->id);
        $this->service()->approve($request->id, $actor);

        $engine = app(LeaveEngine::class);
        $summary = $engine->summary($employee, LeaveType::where('code', 'HL')->sole(), 2026);

        // Plan 7.3.7: show 56, not the raw 60. Showing 60 means the employee
        // requests 60, is refused at 56, and complains to HR rather than
        // understanding why.
        $this->assertSame(60.0, (float) $summary['pool']['cap']);
        $this->assertSame(4.0, (float) $summary['pool']['used']);
        $this->assertSame(56.0, (float) $summary['pool']['available']);
        $this->assertSame(56.0, (float) $summary['available']);
    }

    // ── Legacy data ────────────────────────────────────────────────────────

    public function test_requests_created_before_the_engine_still_count_towards_the_balance(): void
    {
        config(['leave.engine_enabled' => true]);

        $employee = $this->employee(['hire_date' => '2026-01-01']); // 8 days

        // A request as it would exist in production today: days_count, no
        // leave_days rows.
        LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->annual()->id,
            'start_date' => '2026-02-02',
            'end_date' => '2026-02-06',
            'days_count' => 5,
            'status' => 'approved',
        ]);

        $summary = app(LeaveEngine::class)->summary($employee, $this->annual(), 2026);

        // Legacy rows must not be invisible to the new balance calculation, or
        // every employee would appear to have their full entitlement again.
        $this->assertSame(5.0, (float) $summary['taken']);
        $this->assertSame(3.0, (float) $summary['available']);
    }
}
