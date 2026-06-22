<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
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

        if (!empty($filters['search'])) $query->search($filters['search']);
        if (!empty($filters['department_id'])) $query->where('department_id', $filters['department_id']);
        if (!empty($filters['category'])) $query->byCategory($filters['category']);
        if (!empty($filters['status'])) $query->where('status', $filters['status']);

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

        $employee->update($data);

        return $employee->load(['department:id,name', 'designation:id,name', 'manager:id,first_name,last_name']);
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
