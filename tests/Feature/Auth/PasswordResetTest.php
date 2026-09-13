<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
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
     * Case 1: A registered email receives a success response and a token is created.
     */
    public function test_forgot_password_returns_success_for_a_registered_email(): void
    {
        $user = $this->user();

        $this->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertStatus(200);

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    /**
     * Case 2: An unregistered email also receives a success response, preventing
     * email enumeration attacks. No reset token row should be created.
     */
    public function test_forgot_password_returns_the_same_success_response_for_an_unregistered_email(): void
    {
        $nonExistentEmail = 'never-exists-'.uniqid().'@mge-eng.com';

        $response = $this->postJson('/api/forgot-password', ['email' => $nonExistentEmail]);

        $response->assertStatus(200);
        $this->assertSame('If that email is registered, a password reset link has been sent.', $response->json('message'));

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $nonExistentEmail]);
    }

    /**
     * Case 3: Invalid email formats are rejected with a 422.
     */
    public function test_forgot_password_rejects_an_invalid_email_format(): void
    {
        $this->postJson('/api/forgot-password', ['email' => 'not-an-email'])
            ->assertStatus(422);
    }

    /**
     * Case 4: Password reset succeeds when provided with a valid token,
     * correct email, and matching password + confirmation.
     */
    public function test_reset_password_succeeds_with_a_valid_token(): void
    {
        $user = $this->user();
        $newPassword = 'SecureNewPass1';

        $token = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
            'token' => $token,
        ])
            ->assertStatus(200);

        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
    }

    /**
     * Case 5: Reset is rejected (422) when presented with a bogus token,
     * and the user's password remains unchanged.
     */
    public function test_reset_password_rejects_an_invalid_token(): void
    {
        $user = $this->user();

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'password' => 'AnotherSecurePass1',
            'password_confirmation' => 'AnotherSecurePass1',
            'token' => 'bogus-token-string',
        ])
            ->assertStatus(422);

        $this->assertTrue(Hash::check('x', $user->fresh()->password));
    }

    /**
     * Case 6: Reset is rejected (422) when password and password_confirmation don't match.
     */
    public function test_reset_password_rejects_mismatched_password_confirmation(): void
    {
        $user = $this->user();
        $token = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'password' => 'ValidPassword1',
            'password_confirmation' => 'MismatchedPassword1',
            'token' => $token,
        ])
            ->assertStatus(422);
    }

    /**
     * Case 7: Reset is rejected (422) when the new password is shorter than 8 characters.
     */
    public function test_reset_password_rejects_a_password_shorter_than_8_characters(): void
    {
        $user = $this->user();
        $token = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'password' => 'short1',
            'password_confirmation' => 'short1',
            'token' => $token,
        ])
            ->assertStatus(422);
    }
}
