<?php

namespace Tests\Feature\Training;

use App\Models\Employee;
use App\Models\TrainingRequest;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TrainingRequestNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['notifications.email_enabled' => true]);
        Permission::findOrCreate('training.request', 'web');
        Permission::findOrCreate('training.approve', 'web');
    }

    private function user(string $name, array $permissions = [], string $status = 'active'): User
    {
        $user = User::create([
            'first_name' => $name,
            'last_name' => 'User',
            'email' => strtolower($name).'-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('password'),
            'status' => $status,
        ]);

        $user->givePermissionTo($permissions);

        return $user;
    }

    private function employee(User $user, string $number): Employee
    {
        return Employee::create([
            'employee_no' => $number,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'category' => 'office',
            'user_id' => $user->id,
        ]);
    }

    public function test_unauthenticated_and_unauthorized_requests_are_distinguished(): void
    {
        $this->postJson('/api/training/requests', ['title' => 'First Aid'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');

        $user = $this->user('NoPermission');
        $this->employee($user, 'EMP-NO-PERM');

        $this->actingAs($user)
            ->postJson('/api/training/requests', ['title' => 'First Aid'])
            ->assertForbidden()
            ->assertJsonPath('message', 'You do not have permission to perform this action.');
    }

    public function test_request_is_owned_by_authenticated_users_linked_employee_and_emails_requester_and_approvers(): void
    {
        Notification::fake();

        $requester = $this->user('Requester', ['training.request']);
        $requesterEmployee = $this->employee($requester, 'EMP-REQUESTER');

        $other = $this->user('Other');
        $otherEmployee = $this->employee($other, 'EMP-OTHER');

        $firstApprover = $this->user('HrOne', ['training.approve']);
        $secondApprover = $this->user('HrTwo', ['training.approve']);
        $inactiveApprover = $this->user('InactiveHr', ['training.approve'], 'inactive');
        $unrelatedUser = $this->user('Unrelated');

        $response = $this->actingAs($requester)->postJson('/api/training/requests', [
            'employee_id' => $otherEmployee->id,
            'title' => 'Confined Space Safety',
            'category' => 'Safety',
            'reason' => 'Required for site work',
        ]);

        $response->assertCreated();

        $trainingRequest = TrainingRequest::sole();
        $this->assertSame($requesterEmployee->id, $trainingRequest->employee_id);
        $this->assertSame($requester->id, $trainingRequest->created_by);

        Notification::assertSentTo($requester, SystemNotification::class, function (SystemNotification $notification) use ($requester, $trainingRequest) {
            $data = $notification->toArray($requester);

            return $notification->via($requester) === ['database', 'mail']
                && $data['title'] === 'Training request submitted'
                && $data['link'] === '/training/my'
                && $data['extra']['training_request_id'] === $trainingRequest->id;
        });

        foreach ([$firstApprover, $secondApprover] as $approver) {
            Notification::assertSentTo($approver, SystemNotification::class, function (SystemNotification $notification) use ($approver, $trainingRequest) {
                $data = $notification->toArray($approver);

                return $notification->via($approver) === ['database', 'mail']
                    && $data['title'] === 'New training request'
                    && $data['link'] === '/hr/training'
                    && $data['extra']['training_request_id'] === $trainingRequest->id;
            });
        }

        Notification::assertNotSentTo($inactiveApprover, SystemNotification::class);
        Notification::assertNotSentTo($unrelatedUser, SystemNotification::class);

        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $requester->id,
            'type' => 'training',
            'channel' => 'mail',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $firstApprover->id,
            'type' => 'training',
            'channel' => 'mail',
            'status' => 'sent',
        ]);
    }

    public function test_linked_employee_is_required_and_no_notification_is_sent_on_failure(): void
    {
        Notification::fake();
        $requester = $this->user('Unlinked', ['training.request']);

        $this->actingAs($requester)
            ->postJson('/api/training/requests', ['title' => 'First Aid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee');

        $this->assertDatabaseCount('training_requests', 0);
        Notification::assertNothingSent();
    }
}
