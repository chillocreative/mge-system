<?php

namespace Tests\Feature\Training;

use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * HRDF needs 14 days' notice, so a training request must be filed at least 16 days
 * before the training date (3 weeks recommended). Days are counted in Malaysia time.
 */
class TrainingRequestNoticeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('training.request', 'web');

        $this->user = User::create([
            'first_name' => 'Staff',
            'last_name' => 'Member',
            'email' => 'staff@mge-eng.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->user->givePermissionTo('training.request');

        Employee::create([
            'employee_no' => 'EMP-NOTICE',
            'first_name' => 'Staff',
            'last_name' => 'Member',
            'category' => 'office',
            'user_id' => $this->user->id,
        ]);
    }

    private function inDays(int $days): string
    {
        return now('Asia/Kuala_Lumpur')->startOfDay()->addDays($days)->toDateString();
    }

    private function submit(?string $date)
    {
        return $this->actingAs($this->user)->postJson('/api/training/requests', array_filter([
            'title' => 'Confined Space Safety',
            'preferred_date' => $date,
        ]));
    }

    public function test_training_date_is_required(): void
    {
        $this->submit(null)->assertUnprocessable()->assertJsonValidationErrors('preferred_date');

        $this->assertDatabaseCount('training_requests', 0);
    }

    public function test_request_less_than_16_days_ahead_is_refused_with_a_clear_message(): void
    {
        foreach ([-3, 0, 7, 15] as $days) {
            $this->submit($this->inDays($days))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('preferred_date');
        }

        $message = $this->submit($this->inDays(15))->json('errors.preferred_date.0');
        $this->assertStringContainsString('at least 16 days', $message);
        $this->assertStringContainsString('3 weeks', $message);
        $this->assertStringContainsString('HRDF', $message);
        $this->assertStringContainsString(Carbon::parse($this->inDays(16))->format('j M Y'), $message);

        $this->assertDatabaseCount('training_requests', 0);
    }

    public function test_request_exactly_16_days_ahead_is_accepted(): void
    {
        $this->submit($this->inDays(16))->assertCreated();

        $this->assertDatabaseHas('training_requests', ['title' => 'Confined Space Safety', 'status' => 'pending']);
    }

    public function test_request_three_weeks_or_more_ahead_is_accepted(): void
    {
        $this->submit($this->inDays(21))->assertCreated();
        $this->submit($this->inDays(90))->assertCreated();

        $this->assertDatabaseCount('training_requests', 2);
    }

    public function test_days_are_counted_from_today_in_malaysia_time_not_utc(): void
    {
        // 18:00 UTC on 1 Jan is already 02:00 on 2 Jan in Malaysia.
        $this->travelTo(Carbon::parse('2027-01-01 18:00:00', 'UTC'));

        $this->submit('2027-01-17')->assertUnprocessable();
        $this->submit('2027-01-18')->assertCreated();
    }

    public function test_notice_rules_are_sent_to_the_my_training_page(): void
    {
        $this->travelTo(Carbon::parse('2027-03-01 02:00:00', 'Asia/Kuala_Lumpur'));

        $this->actingAs($this->user)->getJson('/api/training/my')
            ->assertOk()
            ->assertJsonPath('data.notice', [
                'min_days' => 16,
                'recommended_days' => 21,
                'hrdf_days' => 14,
                'earliest_date' => '2027-03-17',
                'recommended_date' => '2027-03-22',
            ]);
    }

    public function test_notice_rules_are_sent_even_without_a_linked_employee(): void
    {
        $unlinked = User::create([
            'first_name' => 'No',
            'last_name' => 'Profile',
            'email' => 'noprofile@mge-eng.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $unlinked->givePermissionTo('training.request');

        $this->actingAs($unlinked)->getJson('/api/training/my')
            ->assertOk()
            ->assertJsonPath('data.employee', null)
            ->assertJsonPath('data.notice.min_days', 16);
    }
}
