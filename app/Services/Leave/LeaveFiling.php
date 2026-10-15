<?php

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who may file a leave request for whom.
 *
 *  - Everyone files for themselves.
 *  - HR (leave.manage) files for anyone.
 *  - A Site Clerk (designation "Site Clerk") files for the site staff of their own department,
 *    many of whom have no login, and records the approval the head/PM gave by email or WhatsApp.
 */
class LeaveFiling
{
    public function isSiteClerk(User $user): bool
    {
        $match = fn ($q) => $q->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim((string) config('leave.site_clerk_designation')))]);

        return User::whereKey($user->id)->whereHas('designation', $match)->exists()
            || Employee::where('user_id', $user->id)->whereHas('designation', $match)->exists();
    }

    /**
     * Active Site Clerks (designation on the account or the HR staff record).
     *
     * @return Collection<int, User>
     */
    public function siteClerks(): Collection
    {
        $match = fn ($q) => $q->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim((string) config('leave.site_clerk_designation')))]);

        $ids = User::whereHas('designation', $match)->pluck('id')
            ->merge(Employee::whereNotNull('user_id')->whereHas('designation', $match)->pluck('user_id'))
            ->unique();

        return User::where('status', 'active')->whereIn('id', $ids)->get(['id', 'first_name', 'last_name', 'department_id']);
    }

    /**
     * @return Collection<int, Employee>
     */
    public function fileableEmployees(User $actor): Collection
    {
        $columns = ['id', 'employee_no', 'first_name', 'last_name', 'category', 'department_id', 'user_id'];

        if ($actor->can('leave.manage')) {
            return Employee::where('status', 'active')->orderBy('first_name')->get($columns);
        }

        $own = Employee::where('user_id', $actor->id)->get($columns);

        if (! $this->isSiteClerk($actor)) {
            return $own;
        }

        $department = $this->departmentOf($actor);

        $siteStaff = $department
            ? Employee::where('status', 'active')->where('category', 'site')->where('department_id', $department)->orderBy('first_name')->get($columns)
            : collect();

        return $own->merge($siteStaff)->unique('id')->values();
    }

    public function canFileFor(User $actor, Employee $employee): bool
    {
        return $this->fileableEmployees($actor)->contains('id', $employee->id);
    }

    /**
     * Recording an approval received outside the system: HR, or the Site Clerk for site staff.
     * Never for one's own leave.
     */
    public function canRecordOfflineApproval(User $actor, Employee $employee): bool
    {
        if ($employee->user_id && (int) $employee->user_id === (int) $actor->id) {
            return false;
        }

        return $actor->can('leave.manage') || ($this->isSiteClerk($actor) && $this->canFileFor($actor, $employee));
    }

    public function departmentOf(User $user): ?int
    {
        return $user->department_id ?: Employee::where('user_id', $user->id)->value('department_id');
    }
}
