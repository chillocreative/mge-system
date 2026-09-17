<?php

namespace App\Services\MonthlyReport\Sections;

use App\Models\CorrespondenceType;
use App\Models\ProjectCorrespondence;
use App\Services\MonthlyReport\ReportContext;

final class PendingCorrespondenceBuilder extends AbstractBuilder
{
    public function build(ReportContext $ctx): array
    {
        $clientCode = str_contains($ctx->project->code ?? '', '/')
            ? strtoupper(strtok($ctx->project->code, '/'))
            : strtoupper((string) $ctx->project->code);

        $types = CorrespondenceType::whereNotNull('report_group')->orderBy('sort_order')->get();

        $groups = $types->map(function (CorrespondenceType $type) use ($ctx, $clientCode) {
            $correspondences = ProjectCorrespondence::where('project_id', $ctx->project->id)
                ->where('type', $type->code)
                ->where('status', '!=', 'closed')
                ->orderBy('raised_date')
                ->get();

            $rows = $correspondences->values()->map(fn (ProjectCorrespondence $c, int $index) => [
                'no' => $index + 1,
                'reference' => $c->reference_no,
                'title' => $c->title,
                'issued' => $c->raised_date?->format('d/m/Y'),
                'approved' => $c->actual_close_date?->format('d/m/Y') ?? '-',
                'reminder' => '-',
                'status' => strtoupper($c->status === 'others' && $c->other_status_text ? $c->other_status_text : $c->status),
            ])->all();

            return [
                'code' => $type->code,
                'label' => $type->full_name,
                'ref_prefix' => "MGE/{$clientCode}/".strtoupper($type->code).'/(No)',
                'rows' => $rows,
            ];
        })->values()->all();

        return ['schema' => 1, 'groups' => $groups];
    }
}
