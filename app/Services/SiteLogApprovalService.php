<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\SiteLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SiteLogApprovalService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function eligibleEngineers(): Collection
    {
        return Employee::query()
            ->with(['designation:id,name', 'user:id,first_name,last_name,email'])
            ->where('status', 'active')
            ->whereNotNull('user_id')
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->whereHas('designation', fn ($query) => $query
                ->whereRaw('LOWER(name) LIKE ?', ['%site engineer%']))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (Employee $employee) => [
                'employee_id' => $employee->id,
                'user_id' => $employee->user_id,
                'employee_no' => $employee->employee_no,
                'name' => $employee->full_name,
                'designation' => $employee->designation?->name,
            ]);
    }

    /**
     * @param  array<int, int|string>  $userIds
     */
    public function validateEngineers(array $userIds): void
    {
        $userIds = $this->normaliseIds($userIds);

        if ($userIds === []) {
            return;
        }

        $eligibleIds = Employee::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'active')
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->whereHas('designation', fn ($query) => $query
                ->whereRaw('LOWER(name) LIKE ?', ['%site engineer%']))
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        if (array_diff($userIds, $eligibleIds) !== []) {
            throw ValidationException::withMessages([
                'site_engineer_ids' => 'Every selected user must be an active Site Engineer in the staff register.',
            ]);
        }
    }

    /**
     * Synchronise approvers, reset a prior approval when the assignment set
     * changes, and notify only engineers newly added to that set.
     *
     * @param  array<int, int|string>  $engineerIds
     */
    public function syncAssignments(SiteLog $log, array $engineerIds, bool $creating = false): void
    {
        $engineerIds = $this->normaliseIds($engineerIds);
        $previousEngineerIds = $log->siteEngineers()
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        if ($engineerIds === $previousEngineerIds) {
            return;
        }

        $log->siteEngineers()->sync($engineerIds);

        if (! $creating) {
            $log->forceFill([
                'approval_status' => 'pending',
                'approved_by' => null,
                'approved_at' => null,
            ])->save();
        }

        $newEngineerIds = array_values(array_diff($engineerIds, $previousEngineerIds));

        if ($newEngineerIds === []) {
            return;
        }

        $this->notifications->notifyUserIds(
            $newEngineerIds,
            'Site log awaiting approval',
            "You were assigned to approve the site log for {$log->project->name} dated {$log->log_date->format('d M Y')}.",
            'project',
            "/projects/site-logs?project={$log->project_id}",
            ['project_id' => $log->project_id, 'site_log_id' => $log->id],
            'project',
        );
    }

    public function approve(SiteLog $log, User $user): SiteLog
    {
        abort_unless(
            $log->siteEngineers()->whereKey($user->id)->exists(),
            403,
            'Only an assigned Site Engineer may approve this site log.',
        );

        if ($log->approval_status !== 'approved') {
            $log->update([
                'approval_status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);
        }

        return $log->fresh();
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int, int>
     */
    private function normaliseIds(array $ids): array
    {
        return collect($ids)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
