<?php

namespace App\Repositories\Eloquent;

use App\Models\ProjectReferenceSetting;
use App\Models\ProjectReferenceTemplate;
use App\Repositories\Contracts\ProjectReferenceRepositoryInterface;
use Illuminate\Support\Collection;

class ProjectReferenceRepository implements ProjectReferenceRepositoryInterface
{
    public function settingForProject(int $projectId): ProjectReferenceSetting
    {
        return ProjectReferenceSetting::where('project_id', $projectId)->firstOrFail();
    }

    public function templatesForProject(int $projectId): Collection
    {
        return ProjectReferenceTemplate::where('project_id', $projectId)->orderBy('id')->get();
    }

    public function templateForProject(int $projectId, string $code, bool $lock = false): ProjectReferenceTemplate
    {
        $query = ProjectReferenceTemplate::where('project_id', $projectId)
            ->where('code', strtoupper($code))
            ->where('is_active', true);

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }
}
