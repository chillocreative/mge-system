<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectParty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Parties on a project (Batch 7) — the flexible set a correspondence can be
 * handed between.
 */
class ProjectPartyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProjectParty::query()->orderBy('name');

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return $this->success($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);

        return $this->created(ProjectParty::create($data), 'Party added.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $party = ProjectParty::findOrFail($id);
        $data = $this->validatePayload($request, true);
        $party->update($data);

        return $this->success($party, 'Party updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        ProjectParty::findOrFail($id)->delete();

        return $this->success(null, 'Party removed.');
    }

    private function validatePayload(Request $request, bool $partial = false): array
    {
        $rule = fn (array $r) => $partial ? array_merge(['sometimes'], $r) : $r;

        return $request->validate([
            'project_id' => $rule(['required', 'exists:projects,id']),
            'name' => $rule(['required', 'string', 'max:255']),
            'type' => ['nullable', 'in:client,consultant,main_contractor,subcontractor,supplier,authority,other'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
