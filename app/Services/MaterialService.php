<?php

namespace App\Services;

use App\Repositories\Contracts\MaterialRepositoryInterface;
use Illuminate\Validation\ValidationException;

class MaterialService
{
    public function __construct(private MaterialRepositoryInterface $repository) {}

    public function list(?string $search = null, ?string $category = null, bool $activeOnly = false)
    {
        return $this->repository->list($search, $category, $activeOnly);
    }

    public function create(array $data)
    {
        $data = $this->normalize($data);
        $this->assertUnique($data['category'], $data['description']);

        return $this->repository->create($data);
    }

    public function update(int $id, array $data)
    {
        $material = $this->repository->find($id);
        $data = $this->normalize($data);
        $this->assertUnique($data['category'] ?? $material->category, $data['description'] ?? $material->description, $id);

        return $this->repository->update($material, $data);
    }

    public function assertActivePair(string $category, string $description): void
    {
        if (! $this->repository->activePairExists($category, $description)) {
            throw ValidationException::withMessages(['description' => 'Select an active material description for the selected category.']);
        }
    }

    private function normalize(array $data): array
    {
        foreach (['category', 'description'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = trim($data[$field]);
                if ($data[$field] === '') {
                    throw ValidationException::withMessages([$field => 'This field is required.']);
                }
            }
        }

        return $data;
    }

    private function assertUnique(string $category, string $description, ?int $exceptId = null): void
    {
        if ($this->repository->pairExists($category, $description, $exceptId)) {
            throw ValidationException::withMessages(['description' => 'This category and description already exist.']);
        }
    }
}
