<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\UpdateProjectReferenceSettingsRequest;
use App\Services\ProjectReferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectReferenceController extends Controller
{
    public function __construct(private readonly ProjectReferenceService $service) {}

    public function show(int $project): JsonResponse
    {
        return $this->success($this->service->configuration($project));
    }

    public function update(UpdateProjectReferenceSettingsRequest $request, int $project): JsonResponse
    {
        return $this->success($this->service->updateConfiguration($project, $request->validated()), 'Reference settings updated.');
    }

    public function preview(Request $request, int $project): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:50'],
            'date' => ['nullable', 'date'],
        ]);

        return $this->success($this->service->preview(
            $project,
            $data['type'],
            isset($data['date']) ? now()->parse($data['date']) : null,
        ));
    }

    public function generate(Request $request, int $project): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:50'],
            'date' => ['nullable', 'date'],
        ]);
        $allocation = $this->service->generate(
            $project,
            $data['type'],
            isset($data['date']) ? now()->parse($data['date']) : null,
            $request->user()?->id,
        );

        return $this->created($allocation, 'Reference number allocated.');
    }
}
