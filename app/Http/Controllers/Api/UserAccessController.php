<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

/**
 * Per-user access: grant whole modules directly (on top of the user's role)
 * and flag a user as a system-wide Manager / Director approver.
 */
class UserAccessController extends Controller
{
    /**
     * Permissions grouped by module prefix → ['leave' => ['leave.view', ...], ...]
     */
    private function moduleGroups()
    {
        return Permission::orderBy('name')
            ->get()
            ->groupBy(fn ($p) => explode('.', $p->name)[0])
            ->map(fn ($group) => $group->pluck('name')->all());
    }

    public function show(int $id): JsonResponse
    {
        $user = User::with('roles')->findOrFail($id);
        $direct = $user->getDirectPermissions()->pluck('name');

        // A module is "granted directly" when the user holds every permission in it.
        $modules = $this->moduleGroups()
            ->filter(fn ($perms) => collect($perms)->every(fn ($p) => $direct->contains($p)))
            ->keys()
            ->values();

        return $this->success([
            'modules' => $modules,
            'is_manager' => (bool) $user->is_manager,
            'is_director' => (bool) $user->is_director,
            'role' => $user->roles->pluck('name')->first(),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'modules' => ['present', 'array'],
            'modules.*' => ['string'],
            'is_manager' => ['boolean'],
            'is_director' => ['boolean'],
        ]);

        $isManager = $request->boolean('is_manager');
        $isDirector = $request->boolean('is_director');
        $modules = $validated['modules'];

        $groups = $this->moduleGroups();
        $perms = collect($modules)->flatMap(fn ($m) => $groups[$m] ?? [])->unique()->values()->all();

        // Manager / Director must be able to reach and act on the leave approvals page.
        if ($isManager || $isDirector) {
            $perms = array_values(array_unique(array_merge($perms, ['leave.view', 'leave.approve'])));
        }

        $user->syncPermissions($perms); // direct permissions only — role permissions untouched
        $user->update(['is_manager' => $isManager, 'is_director' => $isDirector]);

        return $this->success(null, 'User access updated.');
    }
}
