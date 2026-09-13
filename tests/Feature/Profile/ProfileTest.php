<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('x'),
            'status' => 'active',
            'avatar' => null,
        ], $overrides));
    }

    /**
     * Case 1: A logged-in user can update their own full_name/email/phone/ic_number.
     */
    public function test_authenticated_user_can_update_profile_fields(): void
    {
        $user = $this->user();
        $newEmail = 'profile-updated-'.uniqid().'@mge-eng.com';

        $this->actingAs($user)
            ->putJson('/api/profile', [
                'full_name' => 'Jane Smith',
                'email' => $newEmail,
                'phone' => '012-3456789',
                'ic_number' => '980101-10-1234',
                'current_password' => 'x',
            ])
            ->assertStatus(200);

        $updated = $user->fresh();
        $this->assertSame('Jane', $updated->first_name);
        $this->assertSame('Smith', $updated->last_name);
        $this->assertSame($newEmail, $updated->email);
        $this->assertSame('012-3456789', $updated->phone);
        $this->assertSame('980101-10-1234', $updated->ic_number);
    }

    /**
     * Case 2: Updating profile fields WITHOUT uploading a new avatar file
     * does NOT clear an existing avatar (regression test for the
     * null-overwrite bug — the 'avatar' key is deliberately present with a
     * null value here, matching what a real empty multipart file field
     * produces, so this faithfully exercises the fix).
     */
    public function test_updating_profile_without_avatar_does_not_clear_existing_avatar(): void
    {
        $user = $this->user(['avatar' => 'avatars/existing.jpg']);

        $this->actingAs($user)
            ->putJson('/api/profile', [
                'full_name' => 'John Doe',
                'email' => $user->email,
                'phone' => null,
                'ic_number' => null,
                'avatar' => null,
            ])
            ->assertStatus(200);

        $this->assertSame('avatars/existing.jpg', $user->fresh()->avatar);
    }

    /**
     * Case 3a: Email uniqueness still blocks taking a DIFFERENT existing user's email.
     */
    public function test_email_uniqueness_blocks_taking_another_users_email(): void
    {
        $userA = $this->user();
        $userB = $this->user(['email' => 'target-'.uniqid().'@mge-eng.com']);

        $this->actingAs($userA)
            ->putJson('/api/profile', [
                'full_name' => 'User A Edited',
                'email' => $userB->email,
                'phone' => null,
                'ic_number' => null,
            ])
            ->assertStatus(422);

        $this->assertNotEquals($userB->email, $userA->fresh()->email);
    }

    /**
     * Case 3b: A user CAN keep submitting their own current unchanged email
     * without a false "already taken" error.
     */
    public function test_user_can_keep_their_own_current_email_during_update(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->putJson('/api/profile', [
                'full_name' => 'Same Email Name',
                'email' => $user->email,
                'phone' => null,
                'ic_number' => null,
            ])
            ->assertStatus(200);

        $this->assertSame($user->email, $user->fresh()->email);
    }

    /**
     * A user attempting to change their email without sending current_password gets rejected.
     */
    public function test_email_change_without_current_password_is_rejected(): void
    {
        $user = $this->user();
        $newEmail = 'no-pw-'.uniqid().'@mge-eng.com';

        $this->actingAs($user)
            ->putJson('/api/profile', [
                'full_name' => 'No Password Sent',
                'email' => $newEmail,
                'phone' => null,
                'ic_number' => null,
            ])
            ->assertStatus(422);

        $this->assertNotEquals($newEmail, $user->fresh()->email);
    }

    /**
     * A user attempting to change their email with the wrong current_password gets rejected.
     */
    public function test_email_change_with_wrong_current_password_is_rejected(): void
    {
        $user = $this->user();
        $newEmail = 'wrong-pw-'.uniqid().'@mge-eng.com';

        $this->actingAs($user)
            ->putJson('/api/profile', [
                'full_name' => 'Wrong Password',
                'email' => $newEmail,
                'phone' => null,
                'ic_number' => null,
                'current_password' => 'DefinitelyWrongPassword',
            ])
            ->assertStatus(422);

        $this->assertNotEquals($newEmail, $user->fresh()->email);
    }

    /**
     * A user changing their email with the correct current_password succeeds.
     */
    public function test_email_change_with_correct_current_password_succeeds(): void
    {
        $user = $this->user();
        $newEmail = 'correct-pw-'.uniqid().'@mge-eng.com';

        $this->actingAs($user)
            ->putJson('/api/profile', [
                'full_name' => 'Correct Password',
                'email' => $newEmail,
                'phone' => null,
                'ic_number' => null,
                'current_password' => 'x',
            ])
            ->assertStatus(200);

        $this->assertSame($newEmail, $user->fresh()->email);
    }

    /**
     * Case 4: Password change succeeds with correct current_password + valid new password + matching confirmation.
     */
    public function test_password_change_succeeds_with_valid_current_and_new_password(): void
    {
        $user = $this->user();
        $newPassword = 'NewSecurePass1';

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->withHeader('Referer', 'http://localhost')
            ->actingAs($user)
            ->putJson('/api/profile/password', [
                'current_password' => 'x',
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])
            ->assertStatus(200);

        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
    }

    /**
     * Case 5: Password change is rejected (422) when current_password is wrong.
     */
    public function test_password_change_rejected_when_current_password_is_wrong(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->putJson('/api/profile/password', [
                'current_password' => 'WrongPassword123',
                'password' => 'NewSecurePass1',
                'password_confirmation' => 'NewSecurePass1',
            ])
            ->assertStatus(422);
    }

    /**
     * Case 6: Password change is rejected (422) when the new password and
     * password_confirmation don't match.
     */
    public function test_password_change_rejected_when_passwords_do_not_match(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->putJson('/api/profile/password', [
                'current_password' => 'x',
                'password' => 'NewSecurePass1',
                'password_confirmation' => 'MismatchedPass1',
            ])
            ->assertStatus(422);
    }

    /**
     * Changing a password invalidates the user's other sessions.
     */
    public function test_password_change_invalidates_other_sessions(): void
    {
        $user = $this->user();

        DB::table('sessions')->insert([
            'id' => 'other-device-session-id',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test-agent',
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->withHeader('Referer', 'http://localhost')
            ->actingAs($user)
            ->putJson('/api/profile/password', [
                'current_password' => 'x',
                'password' => 'NewSecurePass1',
                'password_confirmation' => 'NewSecurePass1',
            ])
            ->assertStatus(200);

        $this->assertDatabaseMissing('sessions', ['id' => 'other-device-session-id']);
    }
}
