<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;

/**
 * Single source of truth for who may decide a staff member's leave / training request.
 *
 *  - The decision belongs to the manager(s) of the requester's own department
 *    (an active user with the "Managers" role / is_manager flag in the same department).
 *  - When the requester is themselves a manager or director, or their department has no
 *    manager, the request goes to the Directors instead.
 *  - Nobody ever decides their own request, and HR has no approval power just by being HR.
 */
class ApprovalRouting
{
    /**
     * Active managers and directors, with each manager's department.
     *
     * @return array{managers: array<int, int>, directors: array<int, int>, department: array<int, int|null>}
     */
    public function roster(): array
    {
        $users = User::where('status', 'active')
            ->where(fn ($q) => $q->where('is_manager', true)
                ->orWhere('is_director', true)
                ->orWhereHas('roles', fn ($r) => $r->whereIn('name', ['Managers', 'Directors'])))
            ->with('roles:id,name')
            ->get(['id', 'department_id', 'is_manager', 'is_director']);

        $managers = $users->filter(fn (User $u) => $u->is_manager || $u->roles->contains('name', 'Managers'));
        $directors = $users->filter(fn (User $u) => $u->is_director || $u->roles->contains('name', 'Directors'));

        // A manager's department comes from their account, else from their HR staff record.
        $fromStaffRecord = Employee::whereIn('user_id', $managers->pluck('id'))->pluck('department_id', 'user_id');

        return [
            'managers' => $managers->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'directors' => $directors->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'department' => $managers->mapWithKeys(fn (User $u) => [
                (int) $u->id => ($u->department_id ?: ($fromStaffRecord[$u->id] ?? null)) ?: null,
            ])->all(),
        ];
    }

    /**
     * User ids allowed to decide this employee's request.
     *
     * @return array{ids: array<int, int>, fallback: bool} fallback = routed to Directors
     */
    public function approversFor(Employee $employee, ?array $roster = null): array
    {
        $roster ??= $this->roster();
        $requesterId = $employee->user_id ? (int) $employee->user_id : null;
        $requesterIsApprover = $requesterId !== null
            && (in_array($requesterId, $roster['managers'], true) || in_array($requesterId, $roster['directors'], true));

        $department = $employee->department_id
            ?: ($requesterId ? User::whereKey($requesterId)->value('department_id') : null);

        if (! $requesterIsApprover && $department) {
            $ids = array_values(array_filter(
                $roster['managers'],
                fn (int $id) => $id !== $requesterId && (int) ($roster['department'][$id] ?? 0) === (int) $department,
            ));

            if ($ids !== []) {
                return ['ids' => $ids, 'fallback' => false];
            }
        }

        return ['ids' => array_values(array_diff($roster['directors'], [$requesterId])), 'fallback' => true];
    }

    public function canDecide(User $actor, Employee $employee, ?array $roster = null): bool
    {
        if ($employee->user_id && (int) $employee->user_id === (int) $actor->id) {
            return false;
        }

        return in_array((int) $actor->id, $this->approversFor($employee, $roster)['ids'], true);
    }

    /**
     * Ids of every employee whose request this user may decide.
     *
     * @return array<int, int>
     */
    public function employeeIdsDecidableBy(User $actor): array
    {
        $roster = $this->roster();

        if (! in_array((int) $actor->id, $roster['managers'], true) && ! in_array((int) $actor->id, $roster['directors'], true)) {
            return [];
        }

        $userDepartments = User::pluck('department_id', 'id');

        return Employee::get(['id', 'user_id', 'department_id'])
            ->filter(function (Employee $employee) use ($actor, $roster, $userDepartments) {
                if (! $employee->department_id && $employee->user_id) {
                    $employee->department_id = $userDepartments[$employee->user_id] ?? null;
                }

                return $this->canDecide($actor, $employee, $roster);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
