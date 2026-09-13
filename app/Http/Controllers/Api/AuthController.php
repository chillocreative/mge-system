<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $this->authService->register($request->validated());

        return $this->success(
            null,
            'Registration successful. Your account is pending admin approval.'
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->login($request->validated());
        $user->load(['department', 'designation', 'roles', 'permissions']);

        Auth::login($user);
        $request->session()->regenerate();

        return $this->success(
            new UserResource($user),
            'Login successful.'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout();

        return $this->success(null, 'Logged out successfully.');
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['department', 'designation', 'roles', 'permissions']);

        return $this->success(new UserResource($user));
    }

    /*
    |--------------------------------------------------------------------------
    | Token-based auth (for native/mobile clients — e.g. Flutter)
    |--------------------------------------------------------------------------
    | The web SPA uses cookie/session auth above. Native apps cannot use
    | cookies, so these endpoints issue a Sanctum Bearer token. Every existing
    | `auth:sanctum` route already accepts `Authorization: Bearer <token>`.
    */

    public function tokenLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        // Reuse the same credential + account-status checks as the SPA login.
        $user = $this->authService->login($validated);
        $user->load(['department', 'designation', 'roles', 'permissions']);

        $device = $validated['device_name'] ?? ($request->userAgent() ?: 'mobile');
        $token = $user->createToken($device)->plainTextToken;

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Login successful.');
    }

    public function tokenLogout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete(); // revoke only the current device's token
        }

        return $this->success(null, 'Logged out successfully.');
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete(); // revoke tokens on all devices

        return $this->success(null, 'Logged out from all devices.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['department', 'designation', 'roles', 'permissions']);

        return $this->success(new UserResource($user));
    }

    /*
    |--------------------------------------------------------------------------
    | Forgot / reset password (public — no auth required)
    |--------------------------------------------------------------------------
    */

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Never branch the response on the broker's status — always answer
        // identically whether the email matches an account or not, so this
        // endpoint can't be used to enumerate registered accounts.
        Password::sendResetLink($request->only('email'));

        return $this->success(
            null,
            'If that email is registered, a password reset link has been sent.'
        );
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => $password])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->success(null, 'Your password has been reset successfully.');
        }

        // Safe to be specific here — reaching this point already required
        // possessing the token from the email, unlike forgotPassword() above.
        return $this->error(match ($status) {
            Password::INVALID_USER => 'We could not find an account with that email address.',
            Password::INVALID_TOKEN => 'This password reset link is invalid or has expired. Please request a new one.',
            Password::RESET_THROTTLED => 'Please wait a moment before trying again.',
            default => 'Unable to reset your password. Please request a new reset link and try again.',
        }, 422);
    }
}
