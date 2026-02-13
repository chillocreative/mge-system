<?php

namespace App\Services;

use App\Models\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ClientService
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository
    ) {}

    public function listClients(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        if ($search) {
            return $this->clientRepository->searchClients($search, $perPage);
        }

        return $this->clientRepository->getActiveClients($perPage);
    }

    public function getClient(int $id): Client
    {
        $client = $this->clientRepository->findOrFail($id);
        $client->load('projects');

        return $client;
    }

    public function createClient(array $data): Client
    {
        return $this->clientRepository->create($data);
    }

    public function updateClient(int $id, array $data): Client
    {
        return $this->clientRepository->update($id, $data);
    }

    public function deleteClient(int $id): bool
    {
        return $this->clientRepository->delete($id);
    }
}
