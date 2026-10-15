<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;

/**
 * Single source of truth for who may decide a staff member's leave / training request.
 *
 *  - The decision belongs to the manager(s) of the requester's own department
 *    (an active user with the "Managers" role / is_manager flag in the same department).
 *  - When the requester is themselves a department head, or their department has no head,
 *    the office's Project Manager(s) (designation "Project Manager", same department) and the
 *    Directors can decide it. A Director's own request goes to the other Directors only.
 *  - Nobody ever decides their own request, and HR has no approval power just by being HR.
 */
class ApprovalRouting
{
    public const PROJECT_MANAGER_DESIGNATION = 'project manager';

    // What a Project Manager needs to reach the leave approvals and the training requests pages.
    public const APPROVER_PAGE_PERMISSIONS = ['leave.view', 'leave.approve', 'training.view'];

    /**
     * Active department heads (managers), directors and Project Managers, each with the
     * department they sit in.
     *
     * @return array{managers: array<int, int>, directors: array<int, int>, projectManagers: array<int, int>, department: array<int, int|null>}
     */
    public function roster(): array
    {
        $isProjectManager = fn ($q) => $q->whereRaw('LOWER(TRIM(name)) = ?', [self::PROJECT_MANAGER_DESIGNATION]);

        // A Project Manager is recognised by designation on the account or on the HR staff record.
        $projectManagerIds = User::whereHas('designation', $isProjectManager)->pluck('id')
            ->merge(Employee::whereNotNull('user_id')->whereHas('designation', $isProjectManager)->pluck('user_id'))
            ->unique()->values();

        $users = User::where('status', 'active')
            ->where(fn ($q) => $q->where('is_manager', true)
                ->orWhere('is_director', true)
                ->orWhereHas('roles', fn ($r) => $r->whereIn('name', ['Managers', 'Directors']))
                ->orWhereIn('id', $projectManagerIds))
            ->with('roles:id,name')
            ->get(['id', 'department_id', 'is_manager', 'is_director']);

        $managers = $users->filter(fn (User $u) => $u->is_manager || $u->roles->contains('name', 'Managers'));
        $directors = $users->filter(fn (User $u) => $u->is_director || $u->roles->contains('name', 'Directors'));
        $projectManagers = $users->filter(fn (User $u) => $projectManagerIds->contains($u->id));

        // A person's department comes from their account, else from their HR staff record.
        $located = $managers->merge($projectManagers)->unique('id');
        $fromStaffRecord = Employee::whereIn('user_id', $located->pluck('id'))->pluck('department_id', 'user_id');

        $ids = fn ($users) => $users->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        return [
            'managers' => $ids($managers),
            'directors' => $ids($directors),
            'projectManagers' => $ids($projectManagers),
            'department' => $located->mapWithKeys(fn (User $u) => [
                (int) $u->id => ($u->department_id ?: ($fromStaffRecord[$u->id] ?? null)) ?: null,
            ])->all(),
        ];
    }

    /**
     * User ids allowed to decide this employee's request.
     *
     * @return array{ids: array<int, int>, fallback: bool} fallback = not the department head's decision
     */
    public function approversFor(Employee $employee, ?array $roster = null): array
    {
        $roster ??= $this->roster();
        $requesterId = $employee->user_id ? (int) $employee->user_id : null;
        $requesterIsDirector = $requesterId !== null && in_array($requesterId, $roster['directors'], true);
        $requesterIsHead = $requesterId !== null && in_array($requesterId, $roster['managers'], true);

        $department = $employee->department_id
            ?: ($requesterId ? User::whereKey($requesterId)->value('department_id') : null);

        $inDepartment = fn (array $ids) => array_values(array_filter(
            $ids,
            fn (int $id) => $department && $id !== $requesterId && (int) ($roster['department'][$id] ?? 0) === (int) $department,
        ));

        $heads = $inDepartment($roster['managers']);

        if (! $requesterIsDirector && ! $requesterIsHead && $heads !== []) {
            return ['ids' => $heads, 'fallback' => false];
        }

        // A head's own request, or a department without a head: the office's Project Manager(s),
        // then the Directors. A Director's own request goes to the other Directors only.
        $projectManagers = $requesterIsDirector ? [] : $inDepartment($roster['projectManagers']);
        $directors = array_values(array_diff($roster['directors'], [$requesterId]));

        return ['ids' => array_values(array_unique(array_merge($projectManagers, $directors))), 'fallback' => true];
    }

    public function canDecide(User $actor, Employee $employee, ?array $roster = null): bool
    {
        if ($employee->user_id && (int) $employee->user_id === (int) $actor->id) {
            return false;
        }

        return in_array((int) $actor->id, $this->approversFor($employee, $roster)['ids'], true);
    }

    public function isProjectManager(User $user): bool
    {
        return in_array((int) $user->id, $this->roster()['projectManagers'], true);
    }

    /**
     * Ids of every employee whose request this user may decide.
     *
     * @return array<int, int>
     */
    public function employeeIdsDecidableBy(User $actor): array
    {
        $roster = $this->roster();
        $actorId = (int) $actor->id;

        if (! in_array($actorId, $roster['managers'], true)
            && ! in_array($actorId, $roster['directors'], true)
            && ! in_array($actorId, $roster['projectManagers'], true)) {
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
