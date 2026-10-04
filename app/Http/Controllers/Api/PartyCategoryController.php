<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\PartyCategoryRequest;
use App\Http\Resources\PartyCategoryResource;
use App\Services\PartyCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartyCategoryController extends Controller
{
    public function __construct(private readonly PartyCategoryService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success(PartyCategoryResource::collection($this->service->list($request->boolean('active_only'))));
    }

    public function store(PartyCategoryRequest $request): JsonResponse
    {
        return $this->created(new PartyCategoryResource($this->service->create($request->validated())), 'Category created.');
    }

    public function update(PartyCategoryRequest $request, int $category): JsonResponse
    {
        return $this->success(new PartyCategoryResource($this->service->update($category, $request->validated())), 'Category updated.');
    }

    public function destroy(int $category): JsonResponse
    {
        $this->service->delete($category);

        return $this->success(null, 'Category deleted.');
    }
}
