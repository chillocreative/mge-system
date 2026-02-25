<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function getActiveUsers(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->active()
            ->with(['department', 'designation'])
            ->latest()
            ->paginate($perPage);
    }

    public function getUsersByDepartment(int $departmentId): Collection
    {
        return $this->model->where('department_id', $departmentId)
            ->active()
            ->get();
    }

    public function findByEmail(string $email): ?Model
    {
        return $this->model->where('email', $email)->first();
    }

    public function getAllUsers(int $perPage = 15, ?string $status = null): LengthAwarePaginator
    {
        $query = $this->model->with(['department', 'designation'])->latest();

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }
}
