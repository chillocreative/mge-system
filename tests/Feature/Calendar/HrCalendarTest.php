<?php

namespace Tests\Feature\Calendar;

use App\Models\CompanyEvent;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PublicHoliday;
use App\Models\User;
use App\Services\CalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ciri 13 (aggregate calendar, privacy-scoped) + Ciri 14 (multi-staff events).
 */
class HrCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('leave.view', 'web');
    }

    private function service(): CalendarService
    {
        return app(CalendarService::class);
    }

    private function user(array $perms = []): User
    {
        $u = User::create(['first_name' => 'U', 'last_name' => 'X', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->givePermissionTo($perms);

        return $u;
    }

    private function approvedLeave(Employee $e): void
    {
        $type = LeaveType::firstOrCreate(['code' => 'AL'], ['name' => 'Annual', 'default_days_per_year' => 14]);
        LeaveRequest::create([
            'employee_id' => $e->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-09-10', 'end_date' => '2026-09-12', 'days_count' => 3, 'status' => 'approved',
        ]);
    }

    public function test_hr_sees_everyones_leave_on_the_calendar(): void
    {
        $hr = $this->user(['leave.view']);
        $staffUser = $this->user();
        $emp = Employee::create(['employee_no' => 'E1', 'first_name' => 'Ali', 'category' => 'office', 'user_id' => $staffUser->id]);
        $this->approvedLeave($emp);

        $feed = $this->service()->aggregate(['start' => '2026-09-01', 'end' => '2026-09-30'], $hr);
        $leave = collect($feed)->where('source', 'leave');

        $this->assertCount(1, $leave);
        $this->assertStringContainsString('Ali', $leave->first()['title']);
    }

    public function test_a_colleague_without_hr_access_does_not_see_others_leave(): void
    {
        $emp = Employee::create(['employee_no' => 'E2', 'first_name' => 'Ali', 'category' => 'office', 'user_id' => $this->user()->id]);
        $this->approvedLeave($emp);

        $colleague = $this->user(); // no leave.view, different person
        Employee::create(['employee_no' => 'E3', 'first_name' => 'Siti', 'category' => 'office', 'user_id' => $colleague->id]);

        $feed = $this->service()->aggregate(['start' => '2026-09-01', 'end' => '2026-09-30'], $colleague);

        // Ali's leave must not appear to Siti.
        $this->assertCount(0, collect($feed)->where('source', 'leave'));
    }

    public function test_a_staff_member_sees_only_their_own_leave(): void
    {
        $mineUser = $this->user();
        $mine = Employee::create(['employee_no' => 'E4', 'first_name' => 'Me', 'category' => 'office', 'user_id' => $mineUser->id]);
        $this->approvedLeave($mine);

        $otherEmp = Employee::create(['employee_no' => 'E5', 'first_name' => 'Other', 'category' => 'office', 'user_id' => $this->user()->id]);
        $this->approvedLeave($otherEmp);

        $feed = $this->service()->aggregate(['start' => '2026-09-01', 'end' => '2026-09-30'], $mineUser);
        $leave = collect($feed)->where('source', 'leave');

        $this->assertCount(1, $leave);
        $this->assertStringContainsString('Your leave', $leave->first()['title']);
    }

    public function test_public_holidays_appear_for_everyone(): void
    {
        PublicHoliday::create(['name' => 'Malaysia Day', 'date' => '2026-09-16', 'year' => 2026, 'scope' => 'national']);
        $anyone = $this->user();

        $feed = $this->service()->aggregate(['start' => '2026-09-01', 'end' => '2026-09-30'], $anyone);

        $this->assertTrue(collect($feed)->contains(fn ($e) => $e['source'] === 'holiday' && $e['title'] === 'Malaysia Day'));
    }

    public function test_adding_attendees_to_an_event_notifies_them(): void
    {
        Notification::fake();
        $creator = $this->user();
        $a1User = $this->user();
        $a1 = Employee::create(['employee_no' => 'A1', 'first_name' => 'Att', 'category' => 'office', 'user_id' => $a1User->id]);

        $this->service()->create([
            'title' => 'Site briefing', 'type' => 'meeting',
            'start_datetime' => '2026-09-20 09:00:00',
            'attendee_ids' => [$a1->id],
        ], $creator->id);

        Notification::assertSentTo($a1User, \App\Notifications\SystemNotification::class);
    }

    public function test_a_weekly_event_expands_into_occurrences(): void
    {
        CompanyEvent::create([
            'title' => 'Weekly toolbox', 'type' => 'meeting',
            'start_datetime' => '2026-09-01 08:00:00', 'recurrence' => 'weekly', 'recurrence_until' => '2026-09-30',
            'created_by' => $this->user()->id,
        ]);

        $feed = $this->service()->aggregate(['start' => '2026-09-01', 'end' => '2026-09-30'], $this->user(['leave.view']));
        $toolbox = collect($feed)->where('source', 'event')->where('title', 'Weekly toolbox');

        // 1, 8, 15, 22, 29 September = 5 occurrences.
        $this->assertCount(5, $toolbox);
    }
}
