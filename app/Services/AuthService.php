<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function register(array $data): User
    {
        $name = User::splitName($data['full_name'] ?? '');

        $user = $this->userRepository->create([
            'first_name' => $name['first_name'],
            'last_name' => $name['last_name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'ic_number' => $data['ic_number'] ?? null,
            'status' => 'pending',
        ]);

        return $user;
    }

    public function login(array $credentials): User
    {
        $user = $this->userRepository->findByEmail($credentials['email']);

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            $message = match ($user->status) {
                'pending' => 'Your account is pending admin approval.',
                'rejected' => 'Your registration has been rejected.',
                default => 'Your account is not active. Please contact administrator.',
            };

            throw ValidationException::withMessages([
                'email' => [$message],
            ]);
        }

        return $user;
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
