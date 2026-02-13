<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class UserService
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function listUsers(int $perPage = 15): LengthAwarePaginator
    {
        return $this->userRepository->getActiveUsers($perPage);
    }

    public function getUser(int $id): User
    {
        $user = $this->userRepository->findOrFail($id);
        $user->load(['department', 'designation', 'roles']);

        return $user;
    }

    public function updateUser(int $id, array $data): User
    {
        $user = $this->userRepository->update($id, $data);

        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        return $user->load(['department', 'designation', 'roles']);
    }

    public function deleteUser(int $id): bool
    {
        return $this->userRepository->delete($id);
    }
}
