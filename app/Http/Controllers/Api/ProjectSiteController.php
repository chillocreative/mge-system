<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sites within a project (Ciri 25). Scoped to a project; a site is an optional
 * structured location that operational records can be filed under.
 */
class ProjectSiteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProjectSite::query()->orderBy('name');

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
        return $this->created(ProjectSite::create($this->validatePayload($request)), 'Site added.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $site = ProjectSite::findOrFail($id);
        $site->update($this->validatePayload($request, true));

        return $this->success($site, 'Site updated.');
    }

    /**
     * Delete a site. Safe by construction: operational records reference it with
     * ON DELETE SET NULL, so they survive and fall back to their free-text
     * location — deleting a site never deletes site logs, incidents or permits.
     */
    public function destroy(int $id): JsonResponse
    {
        ProjectSite::findOrFail($id)->delete();

        return $this->success(null, 'Site removed.');
    }

    private function validatePayload(Request $request, bool $partial = false): array
    {
        $rule = fn (array $r) => $partial ? array_merge(['sometimes'], $r) : $r;

        return $request->validate([
            'project_id' => $rule(['required', 'exists:projects,id']),
            'name' => $rule(['required', 'string', 'max:255']),
            'code' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
