<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\RolePresets;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

class UserService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private NotificationService $notifications,
    ) {}

    public function listUsers(int $perPage = 15, ?string $status = null, ?string $search = null): LengthAwarePaginator
    {
        return $this->userRepository->getAllUsers($perPage, $status, $search);
    }

    public function getUser(int $id): User
    {
        $user = $this->userRepository->findOrFail($id);
        $user->load(['department', 'designation', 'reportsTo.designation', 'roles']);

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
            'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
            'emergency_contact_relationship' => $data['emergency_contact_relationship'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'designation_id' => $data['designation_id'] ?? null,
            'status' => 'active',
        ]);

        $user->assignRole($data['role']);
        $this->applyOrgRoleFlags($user, $data['role']);
        $this->applyRolePreset($user, $data['role']);
        $this->syncProjectManagerAccess($user);

        return $user->load(['department', 'designation', 'reportsTo.designation', 'roles']);
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

    private function isProjectManager(User $user): bool
    {
        $user->load('designation');

        return strtolower(trim((string) $user->designation?->name)) === ApprovalRouting::PROJECT_MANAGER_DESIGNATION;
    }

    /**
     * A Project Manager decides leave and training for their office, so they must be able to open
     * those pages whatever role template they were given (the "Projects" role has no leave access
     * at all). Granted while the designation holds and re-applied after a role change (which resets
     * direct permissions); withdrawn when the designation is changed away, except what the user's
     * role template gives anyway. Approving itself never depends on these permissions.
     */
    private function syncProjectManagerAccess(User $user, bool $wasProjectManager = false): void
    {
        $existing = fn (array $names) => Permission::where('guard_name', 'web')->whereIn('name', $names)->pluck('name')->all();

        if ($this->isProjectManager($user)) {
            $user->givePermissionTo($existing(ApprovalRouting::APPROVER_PAGE_PERMISSIONS));

            return;
        }

        if ($wasProjectManager) {
            $keep = RolePresets::for($user->roles()->pluck('name')->first());
            $user->revokePermissionTo($existing(array_values(array_diff(ApprovalRouting::APPROVER_PAGE_PERMISSIONS, $keep))));
        }
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
        $wasProjectManager = $this->isProjectManager($user);

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

        $this->syncProjectManagerAccess($user, $wasProjectManager);

        return $user->load(['department', 'designation', 'reportsTo.designation', 'roles']);
    }

    public function updateDirectReporting(User $user, ?int $reportsToId): User
    {
        if ($reportsToId !== null) {
            $manager = User::query()->active()->findOrFail($reportsToId);
            $seen = [];

            while ($manager) {
                if ($manager->id === $user->id) {
                    throw ValidationException::withMessages([
                        'reports_to_id' => ['This direct-reporting relationship would create a reporting cycle.'],
                    ]);
                }

                if (isset($seen[$manager->id])) {
                    break;
                }

                $seen[$manager->id] = true;
                $manager = $manager->reportsTo;
            }
        }

        $user->update(['reports_to_id' => $reportsToId]);

        return $user->fresh()->load(['department', 'designation', 'reportsTo.designation', 'roles']);
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
        $this->syncProjectManagerAccess($user);

        $this->notifications->notify(
            $user,
            'Account approved',
            'Your account has been approved. Welcome to MGE-PMS!',
            'user',
            '/dashboard',
        );

        return $user->load(['department', 'designation', 'reportsTo.designation', 'roles']);
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
