<?php

namespace App\Services\ReportData;

use App\Models\ProjectResourceCategory;
use App\Support\MachineryTypes;
use Illuminate\Support\Facades\DB;

class ResourceCategoryService
{
    public const DEFAULT_WORKERS = ['General Worker', 'Operator', 'Bar Bender', 'Carpenter', 'Steel Fixer', 'Mason', 'Electrician', 'Plumber', 'Welder', 'Supervisor', 'Other'];

    public const DEFAULT_MACHINERY = MachineryTypes::VALUES;

    /** @return array<int, string> */
    public function namesFor(int $projectId, string $kind): array
    {
        $names = ProjectResourceCategory::where('project_id', $projectId)->where('kind', $kind)->where('active', true)
            ->orderBy('sort_order')->pluck('name')->all();

        return $names ?: ($kind === 'worker' ? self::DEFAULT_WORKERS : self::DEFAULT_MACHINERY);
    }

    /**
     * Add one name to a project's list and return the spelling it is stored under.
     *
     * A name that already exists (in any letter case) is reused — and switched back on if it had
     * been deactivated — so a type is never split across two spellings in the monthly reports.
     * A project that still runs on the built-in list gets the built-ins copied in first; otherwise
     * its list would shrink to just the new name.
     */
    public function addName(int $projectId, string $kind, string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));

        return DB::transaction(function () use ($projectId, $kind, $name) {
            $scope = fn () => ProjectResourceCategory::where('project_id', $projectId)->where('kind', $kind);

            if (! $scope()->exists()) {
                $this->seedDefaults($projectId, $kind);
            }

            $existing = $scope()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
            if ($existing) {
                if (! $existing->active) {
                    $existing->update(['active' => true]);
                }

                return $existing->name;
            }

            ProjectResourceCategory::create([
                'project_id' => $projectId,
                'kind' => $kind,
                'group' => $kind === 'worker' ? 'Tradesman' : null,
                'name' => $name,
                'sort_order' => ((int) $scope()->max('sort_order')) + 1,
                'active' => true,
            ]);

            return $name;
        });
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
