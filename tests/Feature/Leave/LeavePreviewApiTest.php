<?php

namespace Tests\Feature\Leave;

use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\PublicHoliday;
use App\Models\User;
use Database\Seeders\LeavePolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Phase H — the apply form's day-breakdown preview.
 *
 * Backs the panel that shows "5 calendar days, 1 rest day, deducted 4.0", which
 * exists so the difference between an office and a site employee reads as an
 * explanation rather than a bug (plan 27.6b).
 */
class LeavePreviewApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LeavePolicySeeder::class);
        config(['leave.engine_enabled' => true]);

        foreach (['leave.view', 'leave.request', 'leave.manage'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    private function user(array $permissions = []): User
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'u-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('secret'),
            'status' => 'active',
        ]);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function employee(?User $user = null, string $category = 'office'): Employee
    {
        return Employee::create([
            'employee_no' => 'E-'.uniqid(),
            'first_name' => 'Staff',
            'category' => $category,
            'hire_date' => '2015-01-01',
            'user_id' => $user?->id,
        ]);
    }

    public function test_it_returns_the_day_breakdown(): void
    {
        PublicHoliday::create(['name' => 'Malaysia Day', 'date' => '2026-09-16', 'year' => 2026]);

        $user = $this->user(['leave.request']);
        $employee = $this->employee($user);

        $response = $this->actingAs($user)
            ->postJson('/api/leaves/preview', [
                'employee_id' => $employee->id,
                'leave_type_id' => $this->annualId(),
                'start_date' => '2026-09-14',
                'end_date' => '2026-09-20',
            ])
            ->assertOk();

        $this->assertSame(7, $response->json('data.calendar_days'));
        $this->assertSame(4, (int) $response->json('data.deducted_days'));

        // The excluded days come back with reasons, so the form can list them.
        $reasons = collect($response->json('data.exclusions'))->pluck('reason')->all();
        $this->assertContains('public_holiday', $reasons);
        $this->assertContains('rest_day', $reasons);
    }

    public function test_office_and_site_previews_differ_for_the_same_dates(): void
    {
        $admin = $this->user(['leave.manage']);
        $office = $this->employee(null, 'office');
        $site = $this->employee(null, 'site');

        $ask = fn (Employee $e) => $this->actingAs($admin)
            ->postJson('/api/leaves/preview', [
                'employee_id' => $e->id,
                'leave_type_id' => $this->annualId(),
                'start_date' => '2026-09-07',
                'end_date' => '2026-09-13',
            ])->json('data.deducted_days');

        // The exact case the breakdown panel exists to explain.
        $this->assertSame(5, (int) $ask($office));
        $this->assertSame(6, (int) $ask($site));
    }

    public function test_it_reports_a_cross_year_split(): void
    {
        $user = $this->user(['leave.request']);
        $employee = $this->employee($user, 'site');

        $response = $this->actingAs($user)
            ->postJson('/api/leaves/preview', [
                'employee_id' => $employee->id,
                'leave_type_id' => $this->annualId(),
                'start_date' => '2026-12-30',
                'end_date' => '2027-01-02',
            ])->assertOk();

        $this->assertTrue($response->json('data.spans_multiple_years'));
        $this->assertSame(2.0, (float) $response->json('data.deducted_by_year.2026'));
        $this->assertSame(2.0, (float) $response->json('data.deducted_by_year.2027'));

        // A balance per year, since each is charged separately.
        $this->assertNotNull($response->json('data.balances.2026'));
        $this->assertNotNull($response->json('data.balances.2027'));
    }

    public function test_an_employee_cannot_preview_someone_elses_leave(): void
    {
        $user = $this->user(['leave.request']);
        $this->employee($user);
        $colleague = $this->employee();

        // Balances are personal data; previewing a colleague's would leak them.
        $this->actingAs($user)
            ->postJson('/api/leaves/preview', [
                'employee_id' => $colleague->id,
                'leave_type_id' => $this->annualId(),
                'start_date' => '2026-09-14',
                'end_date' => '2026-09-15',
            ])
            ->assertForbidden();
    }

    public function test_an_administrator_can_preview_on_behalf_of_staff(): void
    {
        $admin = $this->user(['leave.manage']);
        $employee = $this->employee();

        $this->actingAs($admin)
            ->postJson('/api/leaves/preview', [
                'employee_id' => $employee->id,
                'leave_type_id' => $this->annualId(),
                'start_date' => '2026-09-14',
                'end_date' => '2026-09-15',
            ])
            ->assertOk();
    }

    public function test_it_says_nothing_when_the_engine_is_disabled(): void
    {
        config(['leave.engine_enabled' => false]);

        $user = $this->user(['leave.request']);
        $employee = $this->employee($user);

        $response = $this->actingAs($user)
            ->postJson('/api/leaves/preview', [
                'employee_id' => $employee->id,
                'leave_type_id' => $this->annualId(),
                'start_date' => '2026-09-14',
                'end_date' => '2026-09-15',
            ])->assertOk();

        // The form hides the panel rather than showing a breakdown that would not
        // match what the server actually deducts.
        $this->assertFalse($response->json('data.engine_enabled'));
    }

    public function test_an_end_date_before_the_start_date_is_rejected(): void
    {
        $user = $this->user(['leave.request']);
        $employee = $this->employee($user);

        $this->actingAs($user)
            ->postJson('/api/leaves/preview', [
                'employee_id' => $employee->id,
                'leave_type_id' => $this->annualId(),
                'start_date' => '2026-09-15',
                'end_date' => '2026-09-14',
            ])
            ->assertStatus(422);
    }

    private function annualId(): int
    {
        return LeaveType::where('code', 'AL')->sole()->id;
    }
}
