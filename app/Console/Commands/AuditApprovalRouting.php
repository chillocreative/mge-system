<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\ApprovalRouting;
use Illuminate\Console\Command;

class AuditApprovalRouting extends Command
{
    protected $signature = 'approvals:audit';

    protected $description = 'Read-only: show who decides leave/training requests per department and what falls back to the Directors';

    public function handle(ApprovalRouting $routing): int
    {
        $roster = $routing->roster();
        $users = User::pluck('department_id', 'id');
        $names = User::get(['id', 'first_name', 'last_name'])->mapWithKeys(fn (User $u) => [$u->id => $u->full_name]);

        $this->line('Directors: '.count($roster['directors']).' | Managers: '.count($roster['managers']).' | Project Managers: '.count($roster['projectManagers']));
        if ($roster['directors'] === []) {
            $this->warn('No active Directors: requests that fall back to Directors have nobody to approve them.');
        }

        $staff = Employee::get(['id', 'user_id', 'department_id'])
            ->groupBy(fn (Employee $e) => $e->department_id ?: ($e->user_id ? ($users[$e->user_id] ?? null) : null) ?: 0);

        $rows = [];
        foreach (Department::orderBy('name')->get(['id', 'name']) as $department) {
            $managers = collect($roster['managers'])
                ->filter(fn (int $id) => (int) ($roster['department'][$id] ?? 0) === (int) $department->id)
                ->map(fn (int $id) => $names[$id] ?? "#{$id}");

            $projectManagers = collect($roster['projectManagers'])
                ->filter(fn (int $id) => (int) ($roster['department'][$id] ?? 0) === (int) $department->id)
                ->map(fn (int $id) => $names[$id] ?? "#{$id}");

            $rows[] = [
                $department->name,
                $staff->get($department->id, collect())->count(),
                $managers->isEmpty() ? 'NONE -> Project Manager / Directors decide' : $managers->implode(', '),
                $projectManagers->isEmpty() ? '-' : $projectManagers->implode(', '),
            ];
        }
        $this->table(['Department', 'Staff', 'Department head who decides', 'Project Manager (for heads / no head)'], $rows);

        $noDepartment = $staff->get(0, collect())->count();
        if ($noDepartment > 0) {
            $this->warn("{$noDepartment} staff record(s) have no department: their requests go to the Directors.");
        }

        $managersWithoutDepartment = collect($roster['managers'])->filter(fn (int $id) => empty($roster['department'][$id]));
        if ($managersWithoutDepartment->isNotEmpty()) {
            $this->warn('Managers with no department (they cannot decide for anyone): '.$managersWithoutDepartment->map(fn (int $id) => $names[$id] ?? "#{$id}")->implode(', '));
        }

        $lacksAccess = User::whereIn('id', $roster['projectManagers'])->get()
            ->filter(fn (User $u) => array_diff(ApprovalRouting::APPROVER_PAGE_PERMISSIONS, $u->getAllPermissions()->pluck('name')->all()) !== []);
        if ($lacksAccess->isNotEmpty()) {
            $this->warn('Project Managers who cannot open the leave/training approval pages (missing '.implode(', ', ApprovalRouting::APPROVER_PAGE_PERMISSIONS).'): '.$lacksAccess->map(fn (User $u) => $u->full_name)->implode(', '));
        }

        $pmWithoutDepartment = collect($roster['projectManagers'])->filter(fn (int $id) => empty($roster['department'][$id]));
        if ($pmWithoutDepartment->isNotEmpty()) {
            $this->warn('Project Managers with no department (they cannot decide for any office): '.$pmWithoutDepartment->map(fn (int $id) => $names[$id] ?? "#{$id}")->implode(', '));
        }

        return self::SUCCESS;
    }
}
