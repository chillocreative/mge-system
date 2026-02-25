<?php

namespace App\Repositories\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function getActiveUsers(int $perPage = 15): LengthAwarePaginator;

    public function getUsersByDepartment(int $departmentId): \Illuminate\Database\Eloquent\Collection;

    public function findByEmail(string $email): ?\Illuminate\Database\Eloquent\Model;

    public function getAllUsers(int $perPage = 15, ?string $status = null): LengthAwarePaginator;
}
