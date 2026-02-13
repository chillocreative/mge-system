<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Services\ClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function __construct(private ClientService $clientService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $search = $request->string('search')->toString() ?: null;

        $clients = $this->clientService->listClients($perPage, $search);

        return $this->success(
            ClientResource::collection($clients)->response()->getData(true)
        );
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = $this->clientService->createClient($request->validated());

        return $this->created(new ClientResource($client), 'Client created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $client = $this->clientService->getClient($id);

        return $this->success(new ClientResource($client));
    }

    public function update(UpdateClientRequest $request, int $id): JsonResponse
    {
        $client = $this->clientService->updateClient($id, $request->validated());

        return $this->success(new ClientResource($client), 'Client updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->clientService->deleteClient($id);

        return $this->success(null, 'Client deleted successfully.');
    }
}
