<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CorrespondenceRfaSubtypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CorrespondenceRfaSubtypeController extends Controller
{
    public function __construct(
        private readonly CorrespondenceRfaSubtypeService $service,
    ) {}

    public function index(): JsonResponse
    {
        return $this->success($this->service->all());
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge([
            'code' => trim((string) $request->input('code')),
            'name' => trim((string) $request->input('name')),
        ]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return $this->created($this->service->create($data), 'RFA subtype created.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return $this->success(null, 'RFA subtype deleted.');
    }
}
