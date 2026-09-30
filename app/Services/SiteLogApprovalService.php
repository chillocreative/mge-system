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

    public function validateEngineer(?int $userId): void
    {
        if ($userId === null) {
            return;
        }

        $eligible = Employee::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereHas('designation', fn ($query) => $query
                ->whereRaw('LOWER(name) LIKE ?', ['%site engineer%']))
            ->exists();

        if (! $eligible) {
            throw ValidationException::withMessages([
                'site_engineer_id' => 'The selected user must be an active Site Engineer in the staff register.',
            ]);
        }
    }

    public function assignmentChanged(SiteLog $log, ?int $previousEngineerId, bool $creating = false): void
    {
        $currentEngineerId = $log->site_engineer_id ? (int) $log->site_engineer_id : null;
        $previousEngineerId = $previousEngineerId ? (int) $previousEngineerId : null;

        if (! $creating && $currentEngineerId === $previousEngineerId) {
            return;
        }

        if (! $creating) {
            $log->forceFill([
                'approval_status' => 'pending',
                'approved_by' => null,
                'approved_at' => null,
            ])->save();
        }

        if (! $currentEngineerId) {
            return;
        }

        $this->notifications->notifyUserIds(
            [$currentEngineerId],
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
        abort_unless((int) $log->site_engineer_id === (int) $user->id, 403, 'Only the assigned Site Engineer may approve this site log.');

        if ($log->approval_status !== 'approved') {
            $log->update([
                'approval_status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);
        }

        return $log->fresh();
    }
}
