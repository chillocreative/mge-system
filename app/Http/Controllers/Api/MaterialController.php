<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MaterialService;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function __construct(private MaterialService $service) {}

    public function index(Request $request)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'category' => ['nullable', 'string', 'max:100'], 'active_only' => ['nullable', 'boolean']]);

        return $this->success($this->service->list($filters['search'] ?? null, $filters['category'] ?? null, (bool) ($filters['active_only'] ?? false)));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['category' => ['required', 'string', 'max:100'], 'description' => ['required', 'string', 'max:255'], 'is_active' => ['sometimes', 'boolean']]);

        return $this->created($this->service->create($data), 'Material created.');
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate(['category' => ['sometimes', 'required', 'string', 'max:100'], 'description' => ['sometimes', 'required', 'string', 'max:255'], 'is_active' => ['sometimes', 'boolean']]);

        return $this->success($this->service->update($id, $data), 'Material updated.');
    }
}
