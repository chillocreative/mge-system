<?php

namespace App\Services\MonthlyReport\Sections;

use App\Models\ReportImage;
use App\Services\MonthlyReport\ReportContext;

final class ProgressPhotoBuilder extends AbstractBuilder
{
    public function build(ReportContext $ctx): array
    {
        $siteAccess = $this->imagesFor($ctx->project->id, 'site_access');
        $keyPlan = $this->imagesFor($ctx->project->id, 'progress_key_plan');

        $periodIds = array_values(array_filter([$ctx->period->id, $ctx->previousPeriod?->id]));

        $progress = ReportImage::where('project_id', $ctx->project->id)
            ->where('section', 'progress')
            ->whereIn('period_id', $periodIds)
            ->orderBy('sort_order')
            ->get();

        $byLabel = $progress->groupBy('label');

        $labels = $byLabel->keys()->sort(function ($a, $b) {
            if ($a === 'Overall Site View') {
                return -1;
            }
            if ($b === 'Overall Site View') {
                return 1;
            }

            return strcmp((string) $a, (string) $b);
        })->values();

        $pairs = $labels->map(function ($label) use ($byLabel, $ctx) {
            $images = $byLabel->get($label, collect());
            $previous = $ctx->previousPeriod ? $images->firstWhere('period_id', $ctx->previousPeriod->id) : null;
            $current = $images->firstWhere('period_id', $ctx->period->id);

            return [
                'label' => $label,
                'previous' => $previous ? $this->imagePayload($ctx->project->id, $previous) : null,
                'current' => $current ? $this->imagePayload($ctx->project->id, $current) : null,
            ];
        })->values()->all();

        return [
            'schema' => 1,
            'site_access' => $siteAccess,
            'key_plan' => $keyPlan,
            'pairs' => $pairs,
        ];
    }

    /** @return array<int, array{id:int, url:string, caption:?string}> */
    private function imagesFor(int $projectId, string $section): array
    {
        return ReportImage::where('project_id', $projectId)
            ->where('section', $section)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ReportImage $image) => $this->imagePayload($projectId, $image))
            ->values()
            ->all();
    }

    private function imagePayload(int $projectId, ReportImage $image): array
    {
        return [
            'id' => $image->id,
            'url' => "/api/projects/{$projectId}/report-images/{$image->id}/view",
            'caption' => $image->caption,
        ];
    }
}
