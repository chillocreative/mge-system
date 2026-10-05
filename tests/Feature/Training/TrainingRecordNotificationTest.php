<?php

namespace Tests\Feature\Training;

use App\Models\Employee;
use App\Models\TrainingRecord;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TrainingRecordNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['notifications.email_enabled' => false]);
        Permission::findOrCreate('training.manage', 'web');
    }

    private function user(string $name, bool $manager = false): User
    {
        $user = User::create([
            'first_name' => $name,
            'last_name' => 'User',
            'email' => strtolower($name).'-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        if ($manager) {
            $user->givePermissionTo('training.manage');
        }

        return $user;
    }

    private function employee(string $number, ?User $user = null): Employee
    {
        return Employee::create([
            'employee_no' => $number,
            'first_name' => $number,
            'category' => 'office',
            'user_id' => $user?->id,
        ]);
    }

    private function payload(Employee $employee, string $date, string $title = 'First Aid'): array
    {
        return ['employee_id' => $employee->id, 'title' => $title, 'training_date' => $date];
    }

    public function test_create_notifies_linked_staff_with_training_record_link_and_id(): void
    {
        Notification::fake();
        $hr = $this->user('Hr', true);
        $staff = $this->user('Staff');
        $employee = $this->employee('TR-1', $staff);

        $response = $this->actingAs($hr)->postJson('/api/training/records', $this->payload($employee, '2026-10-10'))
            ->assertCreated()
            ->assertJsonPath('message', 'Training record added.');

        $recordId = $response->json('data.id');
        Notification::assertSentTo($staff, SystemNotification::class, function (SystemNotification $notification) use ($staff, $recordId) {
            $data = $notification->toArray($staff);

            return $notification->via($staff) === ['database']
                && $data['type'] === 'training'
                && $data['link'] === '/training/my'
                && $data['extra']['training_record_id'] === $recordId;
        });
        Notification::assertSentToTimes($staff, SystemNotification::class, 1);
        Notification::assertNotSentTo($hr, SystemNotification::class);
    }

    public function test_relevant_update_notifies_new_staff_once_and_unrelated_update_does_not_notify(): void
    {
        Notification::fake();
        $hr = $this->user('Hr', true);
        $oldStaff = $this->user('Old');
        $newStaff = $this->user('New');
        $oldEmployee = $this->employee('TR-OLD', $oldStaff);
        $newEmployee = $this->employee('TR-NEW', $newStaff);
        $record = TrainingRecord::create($this->payload($oldEmployee, '2026-10-10'));

        $this->actingAs($hr)->putJson("/api/training/records/{$record->id}", [
            'employee_id' => $newEmployee->id,
            'training_date' => '2026-10-12',
            'title' => 'Updated First Aid',
        ])->assertOk();
        $this->actingAs($hr)->putJson("/api/training/records/{$record->id}", ['notes' => 'Invoice filed'])
            ->assertOk();
        $this->actingAs($hr)->putJson("/api/training/records/{$record->id}", ['title' => 'Updated First Aid'])
            ->assertOk();

        Notification::assertSentToTimes($newStaff, SystemNotification::class, 1);
        Notification::assertNotSentTo($oldStaff, SystemNotification::class);

        $this->actingAs($hr)->putJson("/api/training/records/{$record->id}", ['end_date' => '2026-10-13'])
            ->assertOk();
        Notification::assertSentToTimes($newStaff, SystemNotification::class, 2);
    }

    public function test_unlinked_employee_is_saved_and_warning_is_returned_in_message(): void
    {
        Notification::fake();
        $hr = $this->user('Hr', true);
        $employee = $this->employee('TR-NO-USER');

        $response = $this->actingAs($hr)->postJson('/api/training/records', $this->payload($employee, '2026-10-10'))
            ->assertCreated()
            ->assertJsonPath('success', true);
        $this->assertStringContainsString('Warning: the selected employee has no linked user account', $response->json('message'));
        $this->assertDatabaseHas('training_records', ['id' => $response->json('data.id'), 'employee_id' => $employee->id]);
        Notification::assertNothingSent();

        $update = $this->actingAs($hr)->putJson('/api/training/records/'.$response->json('data.id'), ['training_date' => '2026-10-11'])
            ->assertOk();
        $this->assertStringContainsString('Warning:', $update->json('message'));

        $this->actingAs($hr)->putJson('/api/training/records/'.$response->json('data.id'), ['notes' => 'Receipt filed'])
            ->assertOk()
            ->assertJsonPath('message', 'Training record updated.');
    }

    public function test_same_staff_can_have_two_training_records_on_different_dates(): void
    {
        Notification::fake();
        $hr = $this->user('Hr', true);
        $staff = $this->user('Staff');
        $employee = $this->employee('TR-REPEAT', $staff);

        $first = $this->actingAs($hr)->postJson('/api/training/records', $this->payload($employee, '2026-10-10'))->assertCreated();
        $second = $this->actingAs($hr)->postJson('/api/training/records', $this->payload($employee, '2026-10-20'))->assertCreated();

        $this->assertNotSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(2, TrainingRecord::where('employee_id', $employee->id)->count());
        Notification::assertSentToTimes($staff, SystemNotification::class, 2);
    }
}
