<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\StoreMasterPartyRequest;
use App\Http\Requests\MasterData\UpdateMasterPartyRequest;
use App\Http\Resources\MasterPartyResource;
use App\Services\MasterPartyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterPartyController extends Controller
{
    public function __construct(private readonly MasterPartyService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = array_filter([
            'search' => $request->string('search')->toString(),
            'category' => $request->string('category')->toString(),
        ]);
        if ($request->has('active')) {
            $filters['active'] = $request->boolean('active');
        }

        $parties = $this->service->list($filters, $request->integer('per_page', 15));

        return $this->success(MasterPartyResource::collection($parties)->response()->getData(true));
    }

    public function options(Request $request): JsonResponse
    {
        $options = $this->service->options(
            $request->string('search')->toString() ?: null,
            $request->string('category')->toString() ?: null,
            $request->integer('limit', 20),
        );

        return $this->success($options->map(fn ($party) => ['id' => $party->id, 'name' => $party->name])->values());
    }

    public function show(int $party): JsonResponse
    {
        return $this->success(new MasterPartyResource($this->service->get($party)));
    }

    public function store(StoreMasterPartyRequest $request): JsonResponse
    {
        return $this->created(new MasterPartyResource($this->service->create($request->validated())), 'Party created.');
    }

    public function update(UpdateMasterPartyRequest $request, int $party): JsonResponse
    {
        return $this->success(new MasterPartyResource($this->service->update($party, $request->validated())), 'Party updated.');
    }

    public function destroy(int $party): JsonResponse
    {
        $this->service->delete($party);

        return $this->success(null, 'Party deleted.');
    }
}
