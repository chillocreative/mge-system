<?php

namespace App\Services;

use App\Models\CorrespondenceType;
use App\Models\Project;
use App\Models\ProjectCorrespondence;
use Illuminate\Support\Collection;

class CorrespondenceRegisterService
{
    public function rows(int $projectId, string $type): Collection
    {
        return ProjectCorrespondence::forProject($projectId)
            ->byType($type)
            ->withCount('files')
            ->with(['fromParty:id,name', 'toParty:id,name'])
            ->orderBy('raised_date')
            ->orderBy('id')
            ->get()
            ->values()
            ->map(function (ProjectCorrespondence $c, int $index) {
                $closedDate = $c->actual_close_date ?? $c->consultant_closed_date ?? $c->client_closed_date;

                return [
                    'bil' => $index + 1,
                    'reference_no' => $c->reference_no,
                    'title' => $c->title,
                    'date_issued' => $c->raised_date?->format('Y-m-d'),
                    'date_inspection' => $c->due_date?->format('Y-m-d'),
                    'date_closed' => $closedDate?->format('Y-m-d'),
                    'status' => $c->status === 'others' ? $c->other_status_text : $c->status,
                    'remarks' => $c->response,
                    'attachments' => $c->files_count,
                    'from' => $c->fromParty?->name,
                    'to' => $c->toParty?->name,
                    'id' => $c->id,
                ];
            });
    }

    public function context(int $projectId, string $type): array
    {
        $project = Project::select('id', 'name', 'code')->findOrFail($projectId);
        $correspondenceType = CorrespondenceType::where('code', $type)->firstOrFail();

        return [
            'project' => $project,
            'type' => $correspondenceType,
            'rows' => $this->rows($projectId, $type),
            'generated_at' => now(),
        ];
    }
}
