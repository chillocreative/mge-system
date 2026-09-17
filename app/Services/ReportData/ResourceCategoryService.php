<?php

namespace App\Services\ReportData;

use App\Models\ProjectResourceCategory;

class ResourceCategoryService
{
    public const DEFAULT_WORKERS = ['General Worker', 'Operator', 'Bar Bender', 'Carpenter', 'Steel Fixer', 'Mason', 'Electrician', 'Plumber', 'Welder', 'Supervisor', 'Other'];

    public const DEFAULT_MACHINERY = ['Excavator', 'Bulldozer', 'Crane', 'Compactor', 'Loader', 'Dump Truck', 'Generator', 'Other'];

    /** @return array<int, string> */
    public function namesFor(int $projectId, string $kind): array
    {
        $names = ProjectResourceCategory::where('project_id', $projectId)->where('kind', $kind)->where('active', true)
            ->orderBy('sort_order')->pluck('name')->all();

        return $names ?: ($kind === 'worker' ? self::DEFAULT_WORKERS : self::DEFAULT_MACHINERY);
    }

    public function seedDefaults(int $projectId, string $kind): void
    {
        $defaults = $kind === 'worker' ? self::DEFAULT_WORKERS : self::DEFAULT_MACHINERY;
        foreach ($defaults as $i => $name) {
            ProjectResourceCategory::firstOrCreate(
                ['project_id' => $projectId, 'kind' => $kind, 'name' => $name],
                ['group' => $kind === 'worker' ? 'Tradesman' : null, 'sort_order' => $i, 'active' => true],
            );
        }
    }
}
