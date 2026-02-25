<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function listUsers(int $perPage = 15, ?string $status = null): LengthAwarePaginator
    {
        return $this->userRepository->getAllUsers($perPage, $status);
    }

    public function getUser(int $id): User
    {
        $user = $this->userRepository->findOrFail($id);
        $user->load(['department', 'designation', 'roles']);

        return $user;
    }

    public function createUser(array $data): User
    {
        $user = $this->userRepository->create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'designation_id' => $data['designation_id'] ?? null,
            'status' => 'active',
        ]);

        $user->assignRole($data['role']);

        return $user->load(['department', 'designation', 'roles']);
    }

    public function updateUser(int $id, array $data): User
    {
        $user = $this->userRepository->update($id, $data);

        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        return $user->load(['department', 'designation', 'roles']);
    }

    public function approveUser(int $id, string $role): User
    {
        $user = $this->userRepository->findOrFail($id);

        if ($user->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending users can be approved.'],
            ]);
        }

        $user->update(['status' => 'active']);
        $user->syncRoles([$role]);

        return $user->load(['department', 'designation', 'roles']);
    }

    public function rejectUser(int $id): User
    {
        $user = $this->userRepository->findOrFail($id);

        if ($user->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending users can be rejected.'],
            ]);
        }

        $user->update(['status' => 'rejected']);

        return $user;
    }

    public function deleteUser(int $id): bool
    {
        return $this->userRepository->delete($id);
    }
}
