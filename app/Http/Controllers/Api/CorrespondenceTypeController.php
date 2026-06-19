<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CorrespondenceType;
use App\Models\ProjectCorrespondence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CorrespondenceTypeController extends Controller
{
    private const COLORS = ['gray', 'red', 'orange', 'amber', 'green', 'teal', 'blue', 'indigo', 'purple', 'pink'];

    public function index(): JsonResponse
    {
        return $this->success(
            CorrespondenceType::orderBy('sort_order')->orderBy('id')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'code' => ['nullable', 'string', 'alpha_dash', 'max:50'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', Rule::in(self::COLORS)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['code'] = Str::slug(($data['code'] ?? null) ?: $data['name'], '_');

        if (CorrespondenceType::where('code', $data['code'])->exists()) {
            return $this->error('A type with this code already exists.', 422);
        }

        $data['color'] = $data['color'] ?? 'gray';
        $data['is_active'] = $data['is_active'] ?? true;

        $type = CorrespondenceType::create($data);

        return $this->created($type, 'Correspondence type created.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $type = CorrespondenceType::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:50'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', Rule::in(self::COLORS)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // The code is the stored value on existing correspondence rows — keep it immutable.
        $type->update($data);

        return $this->success($type->fresh(), 'Correspondence type updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $type = CorrespondenceType::findOrFail($id);

        if (ProjectCorrespondence::where('type', $type->code)->exists()) {
            return $this->error('This type is in use by existing correspondence — deactivate it instead.', 422);
        }

        $type->delete();

        return $this->success(null, 'Correspondence type deleted.');
    }
}
