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
            $tasks = $this->taskService->getProjectTasks($projectId, $filters, $request->user());
        } else {
            $tasks = $this->taskService->getMyTasks($request->user()->id, $filters);
        }

        return $this->success(
            TaskResource::collection($tasks)->response()->getData(true)
        );
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $data = collect($request->validated())->except(['assignee_ids', 'attachments'])->toArray();
        $assigneeIds = $request->input('assignee_ids', []);
        $files = $request->file('attachments', []);

        $task = $this->taskService->createTask($data, $assigneeIds, $files);

        return $this->created(new TaskResource($task), 'Task created successfully.');
    }

    public function downloadAttachment(int $attachment)
    {
        return $this->taskService->downloadAttachment($attachment);
    }

    public function show(int $id): JsonResponse
    {
        $task = $this->taskService->getTask($id);

        return $this->success(new TaskResource($task));
    }

    public function update(UpdateTaskRequest $request, int $id): JsonResponse
    {
        $data = collect($request->validated())->except(['assignee_ids'])->toArray();
        $assigneeIds = $request->has('assignee_ids') ? $request->input('assignee_ids', []) : null;

        $task = $this->taskService->updateTask($id, $data, $assigneeIds);

        return $this->success(new TaskResource($task), 'Task updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->taskService->deleteTask($id);

        return $this->success(null, 'Task deleted successfully.');
    }
}
