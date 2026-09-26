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

    public function getAllUsers(int $perPage = 15, ?string $status = null, ?string $search = null): LengthAwarePaginator
    {
        $query = $this->model->with(['department', 'designation', 'roles', 'permissions'])->latest();

        if ($status) {
            $query->where('status', $status);
        }

        $terms = preg_split('/\\s+/', mb_strtolower(trim((string) $search)), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($terms as $term) {
            $like = '%'.$term.'%';
            $query->where(function ($matches) use ($like) {
                $matches->whereRaw('LOWER(first_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
            });
        }

        return $query->paginate($perPage);
    }
}
