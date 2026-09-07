<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EmployeeService
{
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Employee::with([
            'department:id,name',
            'designation:id,name',
            'manager:id,first_name,last_name',
            'user:id,first_name,last_name,email',
        ])->orderByDesc('created_at');

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }
        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }
        if (! empty($filters['category'])) {
            $query->byCategory($filters['category']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }

    public function getOne(int $id): Employee
    {
        return Employee::with([
            'department:id,name',
            'designation:id,name',
            'manager:id,first_name,last_name,employee_no',
            'creator:id,first_name,last_name',
            'user:id,first_name,last_name,email',
        ])->findOrFail($id);
    }

    public function create(array $data, int $userId, ?UploadedFile $photo = null): Employee
    {
        $data['created_by'] = $userId;

        if ($photo) {
            $data['photo_path'] = $photo->store('employees/photos', 'local');
        }

        $employee = Employee::create($data);

        return $employee->load(['department:id,name', 'designation:id,name', 'manager:id,first_name,last_name']);
    }

    public function update(int $id, array $data, ?UploadedFile $photo = null): Employee
    {
        $employee = Employee::findOrFail($id);

        if ($photo) {
            if ($employee->photo_path) {
                Storage::disk('local')->delete($employee->photo_path);
            }
            $data['photo_path'] = $photo->store('employees/photos', 'local');
        }

        // Detect an employment-status change so it can be stamped and propagated
        // to the login account. Compared before the update is applied.
        $statusChanged = array_key_exists('status', $data) && $data['status'] !== $employee->status;

        if ($statusChanged) {
            $data['status_changed_at'] = now();
            $data['status_changed_by'] = auth()->id();
        }

        DB::transaction(function () use ($employee, $data, $statusChanged) {
            $employee->update($data);

            if ($statusChanged) {
                $this->syncLoginAccess($employee);
            }
        });

        return $employee->load(['department:id,name', 'designation:id,name', 'manager:id,first_name,last_name']);
    }

    /**
     * Keep the linked login account in step with employment status.
     *
     * Login is already blocked for any user whose status is not 'active'
     * (AuthService), so making a resigned or inactive employee's account
     * non-active is what actually stops a former employee logging in — the gap
     * the plan raises in Ciri 9. Reactivating restores access.
     *
     * Only ever toggles between 'active' and 'inactive'. A user parked at
     * 'pending', 'suspended' or 'rejected' is left alone: those states are
     * decisions made elsewhere and are not ours to override from here.
     */
    private function syncLoginAccess(Employee $employee): void
    {
        $user = $employee->user;

        if (! $user) {
            return;
        }

        $employed = $employee->status === 'active';

        if (! $employed && $user->status === 'active') {
            $user->update(['status' => 'inactive']);
        } elseif ($employed && $user->status === 'inactive') {
            $user->update(['status' => 'active']);
        }
    }

    public function delete(int $id): void
    {
        $employee = Employee::findOrFail($id);
        $employee->delete();
    }

    public function directory(): Collection
    {
        return Employee::active()
            ->with(['designation:id,name', 'department:id,name'])
            ->orderBy('first_name')
            ->get()
            ->groupBy(fn ($e) => $e->department->name ?? 'Unassigned');
    }
}
