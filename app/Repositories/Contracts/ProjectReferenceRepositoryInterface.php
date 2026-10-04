<?php

namespace App\Repositories\Contracts;

use App\Models\ProjectReferenceSetting;
use App\Models\ProjectReferenceTemplate;
use Illuminate\Support\Collection;

interface ProjectReferenceRepositoryInterface
{
    public function settingForProject(int $projectId): ProjectReferenceSetting;

    public function templatesForProject(int $projectId): Collection;

    public function templateForProject(int $projectId, string $code, bool $lock = false): ProjectReferenceTemplate;
}
