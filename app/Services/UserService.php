<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\RolePresets;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private NotificationService $notifications,
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
            'ic_number' => $data['ic_number'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'designation_id' => $data['designation_id'] ?? null,
            'status' => 'active',
        ]);

        $user->assignRole($data['role']);
        $this->applyOrgRoleFlags($user, $data['role']);
        $this->applyRolePreset($user, $data['role']);

        return $user->load(['department', 'designation', 'roles']);
    }

    /**
     * Keep the leave-approval flags in sync with the org role assigned:
     * "Managers" approve the manager stage, "Directors" the director stage.
     */
    private function applyOrgRoleFlags(User $user, ?string $role): void
    {
        $user->update([
            'is_manager' => $role === 'Managers',
            'is_director' => $role === 'Directors',
        ]);
    }

    /**
     * Seed the user's DIRECT permissions from the role's template. Roles no
     * longer grant permissions; per-user access is edited on the User Access page.
     */
    private function applyRolePreset(User $user, ?string $role): void
    {
        $user->syncPermissions(RolePresets::for($role));
    }

    public function updateUser(int $id, array $data): User
    {
        $user = $this->userRepository->findOrFail($id);
        $oldRole = $user->roles()->pluck('name')->first();

        $user = $this->userRepository->update($id, $data);

        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
            $this->applyOrgRoleFlags($user, $data['role']);
            // Only re-template direct permissions when the role actually changes,
            // so editing a user (same role) preserves their custom access.
            if ($data['role'] !== $oldRole) {
                $this->applyRolePreset($user, $data['role']);
            }
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
        $this->applyOrgRoleFlags($user, $role);
        $this->applyRolePreset($user, $role);

        $this->notifications->notify(
            $user,
            'Account approved',
            'Your account has been approved. Welcome to MGE-PMS!',
            'user',
            '/dashboard',
        );

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
