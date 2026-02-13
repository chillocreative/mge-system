<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'status' => $this->status,
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ]),
            'designation' => $this->whenLoaded('designation', fn () => [
                'id' => $this->designation->id,
                'name' => $this->designation->name,
            ]),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'permissions' => $this->when(
                $this->relationLoaded('permissions') || $this->relationLoaded('roles'),
                fn () => $this->getAllPermissions()->pluck('name')->unique()->values()
            ),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
