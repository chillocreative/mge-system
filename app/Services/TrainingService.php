<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\TrainingRecord;
use App\Models\TrainingRequest;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class TrainingService
{
    public function __construct(private NotificationService $notifications) {}

    // ── Training Records ──

    public function recordsList(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = TrainingRecord::with([
            'employee:id,employee_no,first_name,last_name',
            'creator:id,first_name,last_name',
        ])->orderByDesc('training_date');

        if (! empty($filters['employee_id'])) {
            $query->forEmployee((int) $filters['employee_id']);
        }
        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (isset($filters['hrdf_claimable']) && $filters['hrdf_claimable'] !== '') {
            $query->where('hrdf_claimable', (bool) $filters['hrdf_claimable']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }

    public function createRecord(array $data, int $userId): TrainingRecord
    {
        $data['created_by'] = $userId;
        $record = TrainingRecord::create($data);
        $this->notifyRecordEmployee($record, 'added');

        return $record->load('employee:id,employee_no,first_name,last_name');
    }

    public function updateRecord(int $id, array $data): TrainingRecord
    {
        $record = TrainingRecord::findOrFail($id);
        $record->update($data);
        if ($this->hasRelevantRecordChanges($record)) {
            $this->notifyRecordEmployee($record, 'updated');
        }

        return $record->load('employee:id,employee_no,first_name,last_name');
    }

    public function recordNotificationWarning(TrainingRecord $record): ?string
    {
        if (! $record->wasRecentlyCreated && ! $this->hasRelevantRecordChanges($record)) {
            return null;
        }

        return Employee::whereKey($record->employee_id)->whereNull('user_id')->exists()
            ? 'Warning: the selected employee has no linked user account, so no training notification was sent. Link the staff record to a user account in HR.'
            : null;
    }

    private function hasRelevantRecordChanges(TrainingRecord $record): bool
    {
        return $record->wasChanged([
            'employee_id', 'training_date', 'end_date', 'title',
            'provider', 'category', 'duration_days',
        ]);
    }

    private function notifyRecordEmployee(TrainingRecord $record, string $action): void
    {
        $userId = Employee::whereKey($record->employee_id)->value('user_id');
        if (! $userId) {
            return;
        }

        $this->notifications->notify(
            User::find($userId),
            'Training record '.$action,
            "Your training \"{$record->title}\" on {$record->training_date->format('d M Y')} was {$action} by HR.",
            'training',
            '/training/my',
            ['training_record_id' => $record->id],
            'training',
        );
    }

    public function deleteRecord(int $id): void
    {
        TrainingRecord::findOrFail($id)->delete();
    }

    // ── Staff coverage / dashboard ──

    /**
     * Who has / hasn't attended training, plus headline stats.
     */
    public function overview(): array
    {
        $lastDates = TrainingRecord::selectRaw('employee_id, MAX(training_date) as last_date')
            ->groupBy('employee_id')
            ->pluck('last_date', 'employee_id');

        $staff = Employee::active()
            ->withCount('trainingRecords')
            ->with(['department:id,name', 'designation:id,name'])
            ->orderBy('first_name')
            ->get()
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'employee_no' => $e->employee_no,
                'full_name' => $e->full_name,
                'department' => $e->department?->name,
                'designation' => $e->designation?->name,
                'training_count' => $e->training_records_count,
                'last_training_date' => $lastDates[$e->id] ?? null,
                'trained' => $e->training_records_count > 0,
            ])
            ->values();

        $total = $staff->count();
        $trained = $staff->where('trained', true)->count();

        return [
            'stats' => [
                'total_staff' => $total,
                'trained_staff' => $trained,
                'untrained_staff' => $total - $trained,
                'total_trainings' => TrainingRecord::count(),
                'total_cost' => (float) TrainingRecord::sum('cost'),
                'hrdf_claimable' => TrainingRecord::where('hrdf_claimable', true)->count(),
                'pending_requests' => TrainingRequest::where('status', 'pending')->count(),
            ],
            'staff' => $staff,
        ];
    }

    // ── Training Requests ──

    public function requestsList(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = TrainingRequest::with([
            'employee:id,employee_no,first_name,last_name',
            'reviewer:id,first_name,last_name',
        ])->orderByDesc('created_at');

        if (! empty($filters['employee_id'])) {
            $query->forEmployee((int) $filters['employee_id']);
        }
        if (! empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        return $query->paginate($perPage);
    }

    public function createRequest(array $data, User $requester): TrainingRequest
    {
        $employee = Employee::where('user_id', $requester->id)->first();

        if (! $employee) {
            throw ValidationException::withMessages([
                'employee' => 'No employee profile is linked to your account. Please ask HR to link your staff record.',
            ]);
        }

        $data['employee_id'] = $employee->id;
        $data['created_by'] = $requester->id;
        $data['status'] = 'pending';
        $request = TrainingRequest::create($data);
        $request->load('employee:id,employee_no,first_name,last_name');

        $this->notifications->notify(
            $requester,
            'Training request submitted',
            "Your training request \"{$request->title}\" has been submitted to HR.",
            'training',
            '/training/my',
            ['training_request_id' => $request->id],
            'training',
        );

        $this->notifications->notifyByPermission(
            'training.approve',
            'New training request',
            "{$request->employee->full_name} requested training: {$request->title}",
            'training',
            '/hr/training',
            ['training_request_id' => $request->id],
            'training',
        );

        return $request;
    }

    public function approveRequest(int $id, User $actor, ?string $notes = null): TrainingRequest
    {
        return $this->decideRequest($id, $actor, 'approved', $notes);
    }

    public function rejectRequest(int $id, User $actor, ?string $notes = null): TrainingRequest
    {
        return $this->decideRequest($id, $actor, 'rejected', $notes);
    }

    private function decideRequest(int $id, User $actor, string $status, ?string $notes): TrainingRequest
    {
        $request = TrainingRequest::with('employee:id,first_name,last_name,user_id')->findOrFail($id);

        abort_if($request->status !== 'pending', 422, 'This request has already been reviewed.');

        $request->update([
            'status' => $status,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        $verb = $status === 'approved' ? 'approved' : 'rejected';
        $this->notifications->notify(
            $request->employee->user_id ? User::find($request->employee->user_id) : null,
            "Training request {$verb}",
            "Your training request \"{$request->title}\" was {$verb}.",
            'training',
            '/training/my',
            ['training_request_id' => $request->id],
        );

        return $request->load([
            'employee:id,employee_no,first_name,last_name',
            'reviewer:id,first_name,last_name',
        ]);
    }

    // ── Self-service ──

    public function myEmployee(int $userId): ?Employee
    {
        return Employee::with('designation:id,name')->where('user_id', $userId)->first();
    }

    public function myData(int $employeeId): array
    {
        return [
            'records' => TrainingRecord::forEmployee($employeeId)->orderByDesc('training_date')->get(),
            'requests' => TrainingRequest::forEmployee($employeeId)
                ->with('reviewer:id,first_name,last_name')
                ->orderByDesc('created_at')->get(),
        ];
    }
}
