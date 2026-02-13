<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesignationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Designation::with('department:id,name')->withCount('users');

        if ($search = $request->string('search')->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($departmentId = $request->integer('department_id')) {
            $query->where('department_id', $departmentId);
        }

        $designations = $request->boolean('all')
            ? $query->orderBy('name')->get()
            : $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return $this->success($designations);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $designation = Designation::create($validated);
        $designation->load('department:id,name');

        return $this->created($designation, 'Designation created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $designation = Designation::with('department:id,name')
            ->withCount('users')
            ->findOrFail($id);

        return $this->success($designation);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $designation = Designation::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'department_id' => ['sometimes', 'exists:departments,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $designation->update($validated);
        $designation->load('department:id,name');

        return $this->success($designation, 'Designation updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $designation = Designation::withCount('users')->findOrFail($id);

        if ($designation->users_count > 0) {
            return $this->error('Cannot delete a designation that has users assigned.', 422);
        }

        $designation->delete();

        return $this->success(null, 'Designation deleted successfully.');
    }
}
