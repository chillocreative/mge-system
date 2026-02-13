<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Department::withCount(['users', 'designations']);

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $departments = $request->boolean('all')
            ? $query->orderBy('name')->get()
            : $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return $this->success($departments);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:departments,name'],
            'code' => ['required', 'string', 'max:50', 'unique:departments,code'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $department = Department::create($validated);

        return $this->created($department, 'Department created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $department = Department::withCount(['users', 'designations'])
            ->with('designations:id,name,department_id,is_active')
            ->findOrFail($id);

        return $this->success($department);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $department = Department::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:departments,name,' . $department->id],
            'code' => ['sometimes', 'string', 'max:50', 'unique:departments,code,' . $department->id],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $department->update($validated);

        return $this->success($department, 'Department updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $department = Department::withCount('users')->findOrFail($id);

        if ($department->users_count > 0) {
            return $this->error('Cannot delete a department that has users assigned.', 422);
        }

        $department->delete();

        return $this->success(null, 'Department deleted successfully.');
    }
}
