<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface CorrespondenceRfaSubtypeRepositoryInterface extends BaseRepositoryInterface
{
    public function ordered(): Collection;

    public function nextSortOrder(): int;

    public function codeExists(string $code): bool;

    public function isReferenced(string $code): bool;
}
