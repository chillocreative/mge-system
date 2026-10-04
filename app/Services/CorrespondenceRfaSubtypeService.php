<?php

namespace App\Services;

use App\Models\CorrespondenceRfaSubtype;
use App\Repositories\Contracts\CorrespondenceRfaSubtypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CorrespondenceRfaSubtypeService
{
    public function __construct(
        private readonly CorrespondenceRfaSubtypeRepositoryInterface $repository,
    ) {}

    public function all(): Collection
    {
        return $this->repository->ordered();
    }

    public function create(array $data): CorrespondenceRfaSubtype
    {
        $data['code'] = Str::upper(trim($data['code']));
        $data['name'] = trim($data['name']);

        if ($this->repository->codeExists($data['code'])) {
            throw ValidationException::withMessages([
                'code' => 'An RFA subtype with this code already exists.',
            ]);
        }

        $data['sort_order'] = $data['sort_order'] ?? $this->repository->nextSortOrder();

        /** @var CorrespondenceRfaSubtype $subtype */
        $subtype = $this->repository->create($data);

        return $subtype;
    }

    public function delete(int $id): void
    {
        /** @var CorrespondenceRfaSubtype $subtype */
        $subtype = $this->repository->findOrFail($id);

        if ($this->repository->isReferenced($subtype->code)) {
            throw ValidationException::withMessages([
                'subtype' => 'This RFA subtype is in use by existing correspondence and cannot be deleted.',
            ]);
        }

        $this->repository->delete($id);
    }
}
