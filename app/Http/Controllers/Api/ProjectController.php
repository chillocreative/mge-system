<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function __construct(private ProjectService $projectService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'priority', 'client_id', 'manager_id', 'search']);
        $perPage = $request->integer('per_page', 15);

        $projects = $this->projectService->listProjects($filters, $perPage);

        return $this->success(
            ProjectResource::collection($projects)->response()->getData(true)
        );
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->projectService->createProject($request->validated());

        return $this->created(new ProjectResource($project), 'Project created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $project = $this->projectService->getProject($id);

        return $this->success(new ProjectResource($project));
    }

    public function update(UpdateProjectRequest $request, int $id): JsonResponse
    {
        $project = $this->projectService->updateProject($id, $request->validated());

        return $this->success(new ProjectResource($project), 'Project updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->projectService->deleteProject($id);

        return $this->success(null, 'Project deleted successfully.');
    }
}
