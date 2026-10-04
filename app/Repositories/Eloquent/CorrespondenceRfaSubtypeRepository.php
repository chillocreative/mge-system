<?php

namespace App\Repositories\Eloquent;

use App\Models\CorrespondenceRfaSubtype;
use App\Models\ProjectCorrespondence;
use App\Repositories\Contracts\CorrespondenceRfaSubtypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CorrespondenceRfaSubtypeRepository extends BaseRepository implements CorrespondenceRfaSubtypeRepositoryInterface
{
    public function __construct(CorrespondenceRfaSubtype $model)
    {
        parent::__construct($model);
    }

    public function ordered(): Collection
    {
        return $this->model->orderBy('sort_order')->orderBy('id')->get();
    }

    public function nextSortOrder(): int
    {
        return ((int) $this->model->max('sort_order')) + 1;
    }

    public function codeExists(string $code): bool
    {
        return $this->model
            ->whereRaw('LOWER(code) = ?', [mb_strtolower($code)])
            ->exists();
    }

    public function isReferenced(string $code): bool
    {
        return ProjectCorrespondence::query()
            ->where('type', 'rfa')
            ->whereRaw('LOWER(document_subtype) = ?', [mb_strtolower($code)])
            ->exists();
    }
}
