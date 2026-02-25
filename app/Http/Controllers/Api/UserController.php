<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $status = $request->query('status');

        $users = $this->userService->listUsers($perPage, $status);

        return $this->success(
            UserResource::collection($users)->response()->getData(true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:20'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'designation_id' => ['nullable', 'exists:designations,id'],
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $user = $this->userService->createUser($validated);

        return $this->created(new UserResource($user), 'User created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $user = $this->userService->getUser($id);

        return $this->success(new UserResource($user));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'designation_id' => ['nullable', 'exists:designations,id'],
            'status' => ['sometimes', 'in:pending,active,inactive,suspended,rejected'],
            'role' => ['nullable', 'string', 'exists:roles,name'],
        ]);

        $user = $this->userService->updateUser($id, $validated);

        return $this->success(new UserResource($user), 'User updated successfully.');
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $user = $this->userService->approveUser($id, $validated['role']);

        return $this->success(new UserResource($user), 'User approved successfully.');
    }

    public function reject(int $id): JsonResponse
    {
        $user = $this->userService->rejectUser($id);

        return $this->success(new UserResource($user), 'User rejected successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->userService->deleteUser($id);

        return $this->success(null, 'User deleted successfully.');
    }
}
