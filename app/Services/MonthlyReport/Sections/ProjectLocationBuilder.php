<?php

namespace App\Services\MonthlyReport\Sections;

use App\Models\ReportImage;
use App\Services\MonthlyReport\ReportContext;

final class ProjectLocationBuilder extends AbstractBuilder
{
    public function build(ReportContext $ctx): array
    {
        $images = ReportImage::where('project_id', $ctx->project->id)
            ->where('section', 'location')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ReportImage $image) => $this->imagePayload($ctx->project->id, $image))
            ->values()
            ->all();

        return [
            'schema' => 1,
            'images' => $images,
        ];
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
