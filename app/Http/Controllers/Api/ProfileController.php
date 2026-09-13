<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Self-service profile management for the currently-authenticated user
 * (Ciri: user profile update). Deliberately separate from UserController,
 * which is the admin-only user-management surface gated by `users.edit` —
 * every method here acts strictly on $request->user() and never accepts or
 * trusts a user id from the request, so there is no way for a logged-in
 * user to affect any account but their own.
 */
class ProfileController extends Controller
{
    /**
     * Update the authenticated user's own profile information.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($request->user()->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'ic_number' => ['nullable', 'string', 'max:50'],
            'avatar' => ['nullable', 'image', 'max:5120'],
        ]);

        // Prevent an empty multipart/null avatar field from silently wiping
        // the existing photo — avatar must only ever change via an actual
        // new file upload (unlike phone/ic_number, there is no legitimate
        // "clear my avatar via a blank field" user action).
        unset($validated['avatar']);

        // Handle avatar replacement if a new file is provided
        if ($request->hasFile('avatar')) {
            $user = $request->user();

            // Delete the existing avatar file matching the EmployeeService pattern
            if ($user->avatar && Storage::disk('local')->exists($user->avatar)) {
                Storage::disk('local')->delete($user->avatar);
            }

            // Store the new avatar
            $path = $request->file('avatar')->store('avatars', 'local');
            $validated['avatar'] = $path;
        }

        // Map full_name into first_name / last_name using the shared helper
        $nameParts = User::splitName($validated['full_name']);
        unset($validated['full_name']);
        $validated = array_merge($validated, $nameParts);

        // Apply updates safely — we are only mutating the authenticated user's row
        $request->user()->update($validated);

        return $this->success(
            new UserResource($request->user()->fresh()->load(['department', 'designation', 'roles', 'permissions'])),
            'Profile updated successfully.'
        );
    }

    /**
     * Update the authenticated user's own password.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update([
            'password' => $validated['password'],
        ]);

        return $this->success(null, 'Password updated successfully.');
    }

    /**
     * Stream the currently authenticated user's stored avatar image.
     */
    public function avatar(Request $request)
    {
        $user = $request->user();

        if (! $user->avatar || ! Storage::disk('local')->exists($user->avatar)) {
            return $this->notFound('Avatar not found.');
        }

        return Storage::disk('local')->response($user->avatar);
    }
}
