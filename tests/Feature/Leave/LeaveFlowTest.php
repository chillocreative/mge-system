<?php

namespace Tests\Feature\Leave;

use App\Models\CompanyEvent;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveDay;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\LeaveStatusNotification;
use App\Services\LeaveService;
use Database\Seeders\LeavePolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The company's real leave flow: annual leave applied for 3 days ahead; HQ staff go to their head,
 * site staff through the Site Clerk (who records the approval the head/PM gave by email or
 * WhatsApp); the head checks the calendar; HR records it and settles the balance, declaring
 * unpaid leave when the balance is used up.
 */
class LeaveFlowTest extends TestCase
{
    use RefreshDatabase;

    private Department $hq;

    private Department $site;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(LeavePolicySeeder::class);
        config(['leave.engine_enabled' => false]);

        foreach (['leave.view', 'leave.request', 'leave.approve', 'leave.manage'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        foreach (['Managers', 'Admin & HR'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->hq = Department::create(['name' => 'Head Office', 'code' => 'HQ']);
        $this->site = Department::create(['name' => 'Site Office', 'code' => 'SITE']);
    }

    private function user(string $name, ?Department $department = null, ?string $role = null, array $permissions = ['leave.view', 'leave.request'], ?string $designation = null): User
    {
        $user = User::create([
            'first_name' => $name,
            'last_name' => 'User',
            'email' => strtolower($name).'-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('secret'),
            'status' => 'active',
            'department_id' => $department?->id,
            'designation_id' => $designation ? Designation::firstOrCreate(['name' => $designation, 'department_id' => ($department ?? $this->hq)->id])->id : null,
        ]);
        $role && $user->assignRole($role);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function employee(?User $user, ?Department $department = null, string $category = 'office', string $name = 'Staff', string $hired = '2026-01-01'): Employee
    {
        return Employee::create([
            'employee_no' => 'E-'.uniqid(),
            'first_name' => $user?->first_name ?? $name,
            'last_name' => 'User',
            'category' => $category,
            'hire_date' => $hired,
            'status' => 'active',
            'user_id' => $user?->id,
            'department_id' => ($department ?? $user?->department)?->id,
        ]);
    }

    private function type(string $code): LeaveType
    {
        return LeaveType::where('code', $code)->sole();
    }

    private function hr(): User
    {
        return $this->user('Hr', null, 'Admin & HR', ['leave.view', 'leave.request', 'leave.manage']);
    }

    private function body(Employee $employee, string $typeCode, int $startInDays, array $extra = [], int $lengthDays = 1): array
    {
        return [
            'employee_id' => $employee->id,
            'leave_type_id' => $this->type($typeCode)->id,
            'start_date' => now()->addDays($startInDays)->toDateString(),
            'end_date' => now()->addDays($startInDays + $lengthDays - 1)->toDateString(),
        ] + $extra;
    }

    // ── 3 days' notice ──

    public function test_annual_leave_must_be_applied_for_three_days_ahead(): void
    {
        $staff = $this->user('Staff', $this->hq);
        $employee = $this->employee($staff);

        $this->actingAs($staff)->postJson('/api/leaves', $this->body($employee, 'AL', 2))
            ->assertUnprocessable()->assertJsonValidationErrors('start_date');

        $this->actingAs($staff)->postJson('/api/leaves', $this->body($employee, 'AL', 3))->assertCreated();
    }

    public function test_other_leave_types_are_not_bound_by_the_notice_rule(): void
    {
        $staff = $this->user('Staff', $this->hq);

        $this->actingAs($staff)->postJson('/api/leaves', $this->body($this->employee($staff), 'MC', 0))->assertCreated();
    }

    public function test_hr_may_file_a_short_notice_exception_only_with_a_reason(): void
    {
        $hr = $this->hr();
        $employee = $this->employee($this->user('Staff', $this->hq));

        $this->actingAs($hr)->postJson('/api/leaves', $this->body($employee, 'AL', 1))
            ->assertUnprocessable()->assertJsonValidationErrors('notice_override_reason');

        $response = $this->actingAs($hr)->postJson('/api/leaves', $this->body($employee, 'AL', 1, ['notice_override_reason' => 'Family emergency']))
            ->assertCreated();

        $this->assertSame('Family emergency', $response->json('data.notice_override_reason'));
    }

    // ── Who may file for whom ──

    public function test_staff_cannot_file_leave_for_someone_else(): void
    {
        $staff = $this->user('Staff', $this->hq);
        $other = $this->employee($this->user('Other', $this->hq));

        $this->actingAs($staff)->postJson('/api/leaves', $this->body($other, 'AL', 5))->assertForbidden();
    }

    public function test_hr_can_file_for_anyone(): void
    {
        $employee = $this->employee(null, $this->site, 'site', 'Worker');

        $this->actingAs($this->hr())->postJson('/api/leaves', $this->body($employee, 'AL', 5))->assertCreated();
    }

    public function test_the_site_clerk_files_only_for_site_staff_of_their_own_department(): void
    {
        $clerk = $this->user('Clerk', $this->site, null, ['leave.view', 'leave.request'], 'Site Clerk');
        $this->employee($clerk, $this->site);

        $siteWorker = $this->employee(null, $this->site, 'site', 'Worker');
        $otherSiteDepartment = $this->employee(null, $this->hq, 'site', 'Elsewhere');
        $officeStaffInSite = $this->employee($this->user('Office', $this->site), $this->site, 'office');

        $this->actingAs($clerk)->postJson('/api/leaves', $this->body($siteWorker, 'AL', 5))->assertCreated();
        $this->actingAs($clerk)->postJson('/api/leaves', $this->body($otherSiteDepartment, 'AL', 5))->assertForbidden();
        $this->actingAs($clerk)->postJson('/api/leaves', $this->body($officeStaffInSite, 'AL', 5))->assertForbidden();
    }

    public function test_the_fileable_employee_list_matches_what_each_person_may_file(): void
    {
        $clerk = $this->user('Clerk', $this->site, null, ['leave.view', 'leave.request'], 'Site Clerk');
        $own = $this->employee($clerk, $this->site);
        $worker = $this->employee(null, $this->site, 'site', 'Worker');
        $this->employee(null, $this->hq, 'site', 'Elsewhere');
        $plain = $this->user('Plain', $this->hq);
        $plainEmployee = $this->employee($plain);

        $clerkView = $this->actingAs($clerk)->getJson('/api/leaves/fileable-employees')->assertOk();
        $this->assertEqualsCanonicalizing([$own->id, $worker->id], array_column($clerkView->json('data.employees'), 'id'));
        $this->assertTrue($clerkView->json('data.can_record_offline_approval'));
        $this->assertFalse($clerkView->json('data.can_override_notice'));

        $plainView = $this->actingAs($plain)->getJson('/api/leaves/fileable-employees')->assertOk();
        $this->assertSame([$plainEmployee->id], array_column($plainView->json('data.employees'), 'id'));
        $this->assertFalse($plainView->json('data.can_record_offline_approval'));
    }

    // ── Approval received by email / WhatsApp ──

    public function test_the_site_clerk_records_an_approval_received_outside_the_system(): void
    {
        Notification::fake();
        $clerk = $this->user('Clerk', $this->site, null, ['leave.view', 'leave.request'], 'Site Clerk');
        $this->employee($clerk, $this->site);
        $worker = $this->employee(null, $this->site, 'site', 'Worker');
        $hr = $this->user('Hr', null, null, ['leave.view', 'leave.manage']);

        $response = $this->actingAs($clerk)->withHeader('Accept', 'application/json')->post('/api/leaves', $this->body($worker, 'AL', 5, [
            'approved_offline' => true,
            'offline_approver_name' => 'Ir. Rahman (PM)',
            'offline_approval_note' => 'WhatsApp 14 Oct',
            'approval_proof' => UploadedFile::fake()->create('whatsapp.png', 20, 'image/png'),
        ]))->assertCreated();

        $leave = LeaveRequest::findOrFail($response->json('data.id'));
        $this->assertSame('approved', $leave->status);
        $this->assertTrue($leave->approved_offline);
        $this->assertSame('Ir. Rahman (PM)', $leave->offline_approver_name);
        $this->assertSame($clerk->id, $leave->created_by);
        $this->assertNotNull($leave->approval_proof_path);
        Storage::disk('local')->assertExists($leave->approval_proof_path);
        $this->assertNull($leave->recorded_at);

        Notification::assertSentTo($hr, LeaveStatusNotification::class, fn ($n) => $n->toArray($hr)['action_type'] === 'leave_info_to_record'
            && $n->toArray($hr)['link'] === '/hr/leave/record');
    }

    public function test_an_offline_approval_needs_the_approvers_name_and_proof(): void
    {
        $clerk = $this->user('Clerk', $this->site, null, ['leave.view', 'leave.request'], 'Site Clerk');
        $this->employee($clerk, $this->site);
        $worker = $this->employee(null, $this->site, 'site', 'Worker');

        $this->actingAs($clerk)->postJson('/api/leaves', $this->body($worker, 'AL', 5, ['approved_offline' => true]))
            ->assertUnprocessable()->assertJsonValidationErrors(['offline_approver_name', 'approval_proof']);
    }

    public function test_only_the_site_clerk_may_record_an_offline_approval_never_hr_nor_for_themselves(): void
    {
        $head = $this->user('Head', $this->hq, 'Managers');
        $headEmployee = $this->employee($head);
        $clerk = $this->user('Clerk', $this->site, null, ['leave.view', 'leave.request'], 'Site Clerk');
        $clerkEmployee = $this->employee($clerk, $this->site);

        $proof = fn () => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf');
        $offline = ['approved_offline' => true, 'offline_approver_name' => 'Someone'];

        $this->actingAs($head)->withHeader('Accept', 'application/json')
            ->post('/api/leaves', $this->body($headEmployee, 'AL', 5, $offline + ['approval_proof' => $proof()]))->assertForbidden();
        $this->actingAs($clerk)->withHeader('Accept', 'application/json')
            ->post('/api/leaves', $this->body($clerkEmployee, 'AL', 5, $offline + ['approval_proof' => $proof()]))->assertForbidden();

        // HR records and settles leave but has no approval power: it cannot create approved leave this way.
        $worker = $this->employee(null, $this->site, 'site', 'Worker');
        $this->actingAs($this->hr())->withHeader('Accept', 'application/json')
            ->post('/api/leaves', $this->body($worker, 'AL', 5, $offline + ['approval_proof' => $proof()]))->assertForbidden();
        $this->assertSame(0, LeaveRequest::where('approved_offline', true)->count());
    }

    public function test_an_offline_approval_cannot_close_a_leave_type_that_needs_the_director(): void
    {
        $clerk = $this->user('Clerk', $this->site, null, ['leave.view', 'leave.request'], 'Site Clerk');
        $this->employee($clerk, $this->site);
        $worker = $this->employee(null, $this->site, 'site', 'Worker');
        $this->type('AL')->update(['requires_director_approval' => true]);

        $this->actingAs($clerk)->withHeader('Accept', 'application/json')->post('/api/leaves', $this->body($worker, 'AL', 5, [
            'approved_offline' => true,
            'offline_approver_name' => 'Ir. Rahman',
            'approval_proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ]))->assertUnprocessable()->assertJsonValidationErrors('approved_offline');
    }

    public function test_the_service_refuses_an_offline_approval_from_anyone_but_a_site_clerk(): void
    {
        $head = $this->user('Head', $this->hq, 'Managers');
        $employee = $this->employee($this->user('Staff', $this->hq));

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(LeaveService::class)->apply([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->type('AL')->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-03',
            'approved_offline' => true,
            'offline_approver_name' => 'Someone',
        ], $head->id);
    }

    // ── Balance used up ──

    public function test_unpaid_leave_has_no_balance_cap(): void
    {
        config(['leave.engine_enabled' => true]);
        $employee = $this->employee(null, $this->hq, 'office', 'Staff');

        $request = app(LeaveService::class)->apply([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->type('UL')->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-04-30',
        ], $this->user('Filer')->id);

        $this->assertEquals(0, $request->shortfall_days);
    }

    public function test_the_preview_reports_how_far_over_the_balance_a_request_goes(): void
    {
        config(['leave.engine_enabled' => true]);
        $staff = $this->user('Staff', $this->hq);
        $employee = $this->employee($staff);

        $response = $this->actingAs($staff)->postJson('/api/leaves/preview', [
            'employee_id' => $employee->id,
            'leave_type_id' => $this->type('AL')->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-27',
        ])->assertOk();

        $this->assertEqualsWithDelta(6.0, $response->json('data.shortfall_days'), 0.01);
    }

    // ── HR records ──

    /** An approved 20-working-day annual request for an employee entitled to 14. */
    private function approvedOverBalance(): LeaveRequest
    {
        config(['leave.engine_enabled' => true]);
        $head = $this->user('Head', $this->hq, 'Managers', ['leave.view', 'leave.approve']);
        $staff = $this->user('Staff', $this->hq);
        $employee = $this->employee($staff);

        $request = app(LeaveService::class)->apply([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->type('AL')->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-27',
        ], $staff->id);

        $this->actingAs($head)->postJson("/api/leaves/{$request->id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');

        return $request->fresh();
    }

    private function available(LeaveRequest $request, string $code = 'AL'): float
    {
        return app(\App\Services\Leave\LeaveEngine::class)->available($request->employee, $this->type($code), 2026);
    }

    public function test_hr_records_an_approved_request_once(): void
    {
        $request = $this->approvedOverBalance();
        $hr = $this->hr();

        $queue = $this->actingAs($hr)->getJson('/api/leaves/to-record')->assertOk();
        $row = collect($queue->json('data.data'))->firstWhere('id', $request->id);
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(-6.0, $row['balance_after'], 0.01);

        $this->actingAs($hr)->postJson("/api/leaves/{$request->id}/record")->assertOk();
        $request->refresh();
        $this->assertSame($hr->id, $request->recorded_by);
        $this->assertNotNull($request->recorded_at);

        $this->actingAs($hr)->postJson("/api/leaves/{$request->id}/record")->assertUnprocessable();
        $this->assertNotContains($request->id, array_column($this->actingAs($hr)->getJson('/api/leaves/to-record')->json('data.data'), 'id'));
    }

    public function test_only_hr_can_record(): void
    {
        $request = $this->approvedOverBalance();
        $head = $this->user('Head2', $this->hq, 'Managers', ['leave.view', 'leave.approve']);

        $this->actingAs($head)->postJson("/api/leaves/{$request->id}/record")->assertForbidden();
        $this->actingAs($head)->getJson('/api/leaves/to-record')->assertForbidden();
    }

    public function test_hr_can_declare_the_whole_request_unpaid(): void
    {
        $request = $this->approvedOverBalance();
        $annual = $this->type('AL');
        $this->assertEqualsWithDelta(-6.0, $this->available($request), 0.01);

        $this->actingAs($this->hr())->postJson("/api/leaves/{$request->id}/record", ['unpaid' => 'all'])->assertOk();

        $request->refresh();
        $this->assertSame($this->type('UL')->id, $request->leave_type_id);
        $this->assertSame($annual->id, $request->original_leave_type_id);
        $this->assertEqualsWithDelta(14.0, $this->available($request), 0.01, 'the annual balance is whole again');
        $this->assertSame(0, LeaveDay::where('leave_request_id', $request->id)->where('leave_type_id', $annual->id)->count());
    }

    public function test_hr_can_declare_only_the_excess_unpaid(): void
    {
        $request = $this->approvedOverBalance();
        $totalDays = (float) $request->days_count;

        $this->actingAs($this->hr())->postJson("/api/leaves/{$request->id}/record", ['unpaid' => 'excess'])->assertOk();

        $paid = $request->fresh();
        $unpaid = LeaveRequest::where('split_from_request_id', $request->id)->sole();

        $this->assertEqualsWithDelta(14.0, (float) $paid->days_count, 0.01);
        $this->assertEqualsWithDelta(6.0, (float) $unpaid->days_count, 0.01);
        $this->assertEqualsWithDelta($totalDays, (float) $paid->days_count + (float) $unpaid->days_count, 0.01);
        $this->assertSame($this->type('UL')->id, $unpaid->leave_type_id);
        $this->assertSame($this->type('AL')->id, $unpaid->original_leave_type_id);
        $this->assertSame('approved', $unpaid->status);
        $this->assertNotNull($unpaid->recorded_at);
        $this->assertTrue($paid->end_date->lt($unpaid->start_date), 'the two parts do not overlap');
        $this->assertEqualsWithDelta(0.0, $this->available($paid), 0.01, 'the whole annual balance is used, none overdrawn');
        $this->assertEqualsWithDelta(0.0, (float) LeaveBalance::where('employee_id', $paid->employee_id)->where('leave_type_id', $this->type('UL')->id)->where('year', 2026)->value('remaining_days'), 0.01, 'unpaid leave never shows a negative balance');
        $this->assertSame(
            (int) LeaveDay::where('leave_request_id', $paid->id)->count() + (int) LeaveDay::where('leave_request_id', $unpaid->id)->count(),
            (int) LeaveDay::whereIn('leave_request_id', [$paid->id, $unpaid->id])->count(),
        );
    }

    public function test_there_is_nothing_to_declare_unpaid_when_the_balance_covers_the_request(): void
    {
        config(['leave.engine_enabled' => true]);
        $head = $this->user('Head', $this->hq, 'Managers', ['leave.view', 'leave.approve']);
        $staff = $this->user('Staff', $this->hq);
        $request = app(LeaveService::class)->apply([
            'employee_id' => $this->employee($staff)->id,
            'leave_type_id' => $this->type('AL')->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-03',
        ], $staff->id);
        $this->actingAs($head)->postJson("/api/leaves/{$request->id}/approve")->assertOk();

        $this->actingAs($this->hr())->postJson("/api/leaves/{$request->id}/record", ['unpaid' => 'excess'])->assertUnprocessable();
        $this->assertNull($request->fresh()->recorded_at);
    }

    public function test_declaring_only_the_excess_needs_the_engine_but_the_whole_request_does_not(): void
    {
        config(['leave.engine_enabled' => false]);
        $head = $this->user('Head', $this->hq, 'Managers', ['leave.view', 'leave.approve']);
        $staff = $this->user('Staff', $this->hq);
        $request = app(LeaveService::class)->apply([
            'employee_id' => $this->employee($staff)->id,
            'leave_type_id' => $this->type('AL')->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-06',
        ], $staff->id);
        $this->actingAs($head)->postJson("/api/leaves/{$request->id}/approve")->assertOk();
        $hr = $this->hr();

        $this->actingAs($hr)->postJson("/api/leaves/{$request->id}/record", ['unpaid' => 'excess'])->assertUnprocessable();
        $this->actingAs($hr)->postJson("/api/leaves/{$request->id}/record", ['unpaid' => 'all'])->assertOk();

        $this->assertSame($this->type('UL')->id, $request->fresh()->leave_type_id);
    }

    // ── The head checks the calendar ──

    public function test_the_approver_sees_what_overlaps_the_requested_dates(): void
    {
        $head = $this->user('Head', $this->hq, 'Managers', ['leave.view', 'leave.approve']);
        $staff = $this->user('Staff', $this->hq);
        $employee = $this->employee($staff);
        $colleague = $this->employee($this->user('Colleague', $this->hq));

        CompanyEvent::create(['title' => 'Board meeting', 'type' => 'meeting', 'start_datetime' => '2026-11-10 09:00:00', 'end_datetime' => '2026-11-10 12:00:00', 'all_day' => false, 'created_by' => $head->id]);

        LeaveRequest::create([
            'employee_id' => $colleague->id, 'leave_type_id' => $this->type('AL')->id,
            'start_date' => '2026-11-10', 'end_date' => '2026-11-11', 'days_count' => 2, 'status' => 'approved', 'created_by' => $colleague->user_id,
        ]);
        $request = LeaveRequest::create([
            'employee_id' => $employee->id, 'leave_type_id' => $this->type('AL')->id,
            'start_date' => '2026-11-09', 'end_date' => '2026-11-12', 'days_count' => 4, 'status' => 'pending',
            'current_approval_level' => 'manager', 'created_by' => $staff->id,
        ]);

        $row = collect($this->actingAs($head)->getJson('/api/leaves/pending-approvals')->assertOk()->json('data.data'))->firstWhere('id', $request->id);

        $kinds = array_column($row['conflicts'], 'kind');
        $this->assertContains('event', $kinds);
        $this->assertContains('team_leave', $kinds);
        $this->assertContains('Board meeting', array_column($row['conflicts'], 'title'));
    }

    public function test_a_balance_snapshot_exists_after_recording_so_staff_see_the_settled_balance(): void
    {
        $request = $this->approvedOverBalance();

        $this->actingAs($this->hr())->postJson("/api/leaves/{$request->id}/record", ['unpaid' => 'excess'])->assertOk();

        $annual = LeaveBalance::where('employee_id', $request->employee_id)->where('leave_type_id', $this->type('AL')->id)->where('year', 2026)->sole();
        $this->assertEqualsWithDelta(0.0, (float) $annual->remaining_days, 0.01);
    }

    public function test_the_clerk_can_follow_what_they_filed_and_hr_can_read_the_proof_but_other_staff_cannot(): void
    {
        $clerk = $this->user('Clerk', $this->site, null, ['leave.view', 'leave.request'], 'Site Clerk');
        $this->employee($clerk, $this->site);
        $worker = $this->employee(null, $this->site, 'site', 'Worker');

        $id = $this->actingAs($clerk)->withHeader('Accept', 'application/json')->post('/api/leaves', $this->body($worker, 'AL', 5, [
            'approved_offline' => true,
            'offline_approver_name' => 'Ir. Rahman',
            'approval_proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ]))->assertCreated()->json('data.id');

        $listed = array_column($this->actingAs($clerk)->getJson('/api/leaves?filed_by_me=1')->assertOk()->json('data.data'), 'id');
        $this->assertContains($id, $listed);
        $this->actingAs($clerk)->getJson("/api/leaves/{$id}")->assertOk();
        $this->actingAs($clerk)->get("/api/leaves/{$id}/approval-proof")->assertOk();
        $this->actingAs($this->hr())->get("/api/leaves/{$id}/approval-proof")->assertOk();

        $stranger = $this->user('Stranger', $this->hq);
        $this->employee($stranger);
        $this->actingAs($stranger)->getJson("/api/leaves/{$id}")->assertNotFound();
        $this->actingAs($stranger)->get("/api/leaves/{$id}/approval-proof")->assertNotFound();
        $this->assertNotContains($id, array_column($this->actingAs($stranger)->getJson('/api/leaves?filed_by_me=1')->json('data.data'), 'id'));
    }

    public function test_the_audit_command_flags_site_staff_who_have_no_site_clerk(): void
    {
        $clerk = $this->user('Clerk', $this->site, null, ['leave.view', 'leave.request'], 'Site Clerk');
        $this->employee($clerk, $this->site);
        $this->employee(null, $this->site, 'site', 'Covered');
        $this->employee(null, $this->hq, 'site', 'Uncovered');

        $this->artisan('approvals:audit')
            ->expectsOutputToContain('Site Clerks: 1')
            ->expectsOutputToContain('1 site staff (Head Office) have no Site Clerk')
            ->assertSuccessful();
    }
}
