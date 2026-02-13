<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions:id,name')
            ->get()
            ->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => $role->users()->count(),
                'permissions' => $role->permissions->pluck('name'),
                'created_at' => $role->created_at?->toISOString(),
            ]);

        return $this->success($roles);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
        $role->syncPermissions($validated['permissions']);

        return $this->created([
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name'),
        ], 'Role created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $role = Role::with('permissions:id,name')->findOrFail($id);

        return $this->success([
            'id' => $role->id,
            'name' => $role->name,
            'users_count' => $role->users()->count(),
            'permissions' => $role->permissions->pluck('name'),
            'created_at' => $role->created_at?->toISOString(),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:roles,name,' . $role->id],
            'permissions' => ['sometimes', 'array', 'min:1'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if (isset($validated['name'])) {
            $role->update(['name' => $validated['name']]);
        }

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return $this->success([
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions()->pluck('name'),
        ], 'Role updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        // Prevent deleting system roles
        $systemRoles = ['Admin & HR', 'Finances & HR', 'Projects'];
        if (in_array($role->name, $systemRoles)) {
            return $this->error('System roles cannot be deleted.', 422);
        }

        if ($role->users()->count() > 0) {
            return $this->error('Cannot delete a role that has users assigned.', 422);
        }

        $role->delete();

        return $this->success(null, 'Role deleted successfully.');
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::orderBy('name')
            ->get()
            ->groupBy(fn ($p) => explode('.', $p->name)[0])
            ->map(fn ($group) => $group->pluck('name'));

        return $this->success($permissions);
    }
}
