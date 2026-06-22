<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-user access: grant individual permissions directly (on top of the user's
 * role) and flag a user as a system-wide Manager / Director approver.
 */
class UserAccessController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $user = User::with('roles')->findOrFail($id);

        return $this->success([
            'direct' => $user->getDirectPermissions()->pluck('name')->values(),
            'role_permissions' => $user->getPermissionsViaRoles()->pluck('name')->unique()->values(),
            'is_manager' => (bool) $user->is_manager,
            'is_director' => (bool) $user->is_director,
            'role' => $user->roles->pluck('name')->first(),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'is_manager' => ['boolean'],
            'is_director' => ['boolean'],
        ]);

        $isManager = $request->boolean('is_manager');
        $isDirector = $request->boolean('is_director');
        $perms = $validated['permissions'];

        // Manager / Director must be able to reach and act on the leave approvals page.
        if ($isManager || $isDirector) {
            $perms = array_values(array_unique(array_merge($perms, ['leave.view', 'leave.approve'])));
        }

        $user->syncPermissions($perms); // direct permissions only — role permissions untouched
        $user->update(['is_manager' => $isManager, 'is_director' => $isDirector]);

        return $this->success(null, 'User access updated.');
    }
}
