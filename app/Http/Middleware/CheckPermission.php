<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Admin & HR bypasses everything via Gate::before
        if ($user->hasRole('Admin & HR')) {
            return $next($request);
        }

        // Flatten, split by comma or pipe, and trim whitespace
        $validPermissions = [];
        foreach ($permissions as $permGroup) {
            $segments = preg_split('/[,|]/', $permGroup);
            foreach ($segments as $segment) {
                $trimmed = trim($segment);
                if ($trimmed !== '') {
                    $validPermissions[] = $trimmed;
                }
            }
        }

        // Check if user has ANY of the parsed permissions
        foreach ($validPermissions as $permission) {
            if ($user->hasPermissionTo($permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'You do not have permission to perform this action.',
            'required_permissions' => $validPermissions,
        ], 403);
    }
}
