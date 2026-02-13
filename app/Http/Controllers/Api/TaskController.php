<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(private TaskService $taskService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'priority', 'assigned_to', 'per_page']);
        $projectId = $request->integer('project_id');

        if ($projectId) {
            $tasks = $this->taskService->getProjectTasks($projectId, $filters);
        } else {
            $tasks = $this->taskService->getMyTasks(auth()->id(), $filters);
        }

        return $this->success(
            TaskResource::collection($tasks)->response()->getData(true)
        );
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $this->taskService->createTask($request->validated());

        return $this->created(new TaskResource($task), 'Task created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $task = $this->taskService->getTask($id);

        return $this->success(new TaskResource($task));
    }

    public function update(UpdateTaskRequest $request, int $id): JsonResponse
    {
        $task = $this->taskService->updateTask($id, $request->validated());

        return $this->success(new TaskResource($task), 'Task updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->taskService->deleteTask($id);

        return $this->success(null, 'Task deleted successfully.');
    }
}
