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
            'permissions' => $user->getDirectPermissions()->pluck('name')->values(),
            'is_manager' => (bool) $user->is_manager,
            'is_director' => (bool) $user->is_director,
            'role' => $user->roles->pluck('name')->first(),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::with('roles')->findOrFail($id);

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'is_manager' => ['boolean'],
            'is_director' => ['boolean'],
        ]);

        $isManager = $request->boolean('is_manager');
        $isDirector = $request->boolean('is_director');
        $perms = $validated['permissions'];

        // A Managers/Directors-role user who is granted leave.approve here is
        // implicitly a system-wide approver for that stage, even though this
        // form (unlike user creation) doesn't otherwise flip the flag.
        if (in_array('leave.approve', $perms, true)) {
            if ($user->hasRole('Managers')) {
                $isManager = true;
            }
            if ($user->hasRole('Directors')) {
                $isDirector = true;
            }
        }

        // Manager / Director must be able to reach and act on the leave approvals page.
        if ($isManager || $isDirector) {
            $perms = array_values(array_unique(array_merge($perms, ['leave.view', 'leave.approve'])));
        }

        $user->syncPermissions($perms); // direct permissions only — role permissions untouched
        $user->update(['is_manager' => $isManager, 'is_director' => $isDirector]);

        return $this->success(null, 'User access updated.');
    }
}
