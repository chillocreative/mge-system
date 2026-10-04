<?php

namespace App\Services;

use App\Models\CorrespondenceNumberRule;
use App\Models\Project;
use App\Models\ProjectCorrespondence;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CorrespondenceNumberService
{
    public function __construct(private readonly ProjectReferenceService $projectReferenceService) {}

    /**
     * Allocate the next reference inside a project/type/subtype scope.
     * Rules are data-driven so an imported Excel convention can be configured
     * without changing application code. Supported tokens are project, type,
     * subtype, yy, yyyy and sequence.
     */
    public function generate(int $projectId, string $type, ?string $subtype, ?int $year = null): string
    {
        if ($referenceType = $this->referenceType($type, $subtype)) {
            $date = now();
            if ($year) {
                $date = $date->setYear($year);
            }

            return $this->projectReferenceService
                ->generate($projectId, $referenceType, $date, auth()->id())
                ->reference_no;
        }

        return DB::transaction(function () use ($projectId, $type, $subtype, $year) {
            $year ??= (int) now()->format('Y');
            $subtype = trim((string) $subtype);

            $rule = CorrespondenceNumberRule::query()
                ->where('project_id', $projectId)
                ->where('correspondence_type', $type)
                ->whereIn('document_subtype', array_values(array_unique([$subtype, ''])))
                ->orderByRaw('CASE WHEN document_subtype = ? THEN 0 ELSE 1 END', [$subtype])
                ->lockForUpdate()
                ->first();

            if (! $rule) {
                $rule = CorrespondenceNumberRule::create([
                    'project_id' => $projectId,
                    'correspondence_type' => $type,
                    'document_subtype' => $subtype,
                    'pattern' => $subtype === ''
                        ? 'MGE/{project}/{type}/{yy}-{sequence}'
                        : 'MGE/{project}/{type}/{subtype}/{yy}-{sequence}',
                    'padding' => 3,
                    'next_number' => 1,
                    'reset_annually' => true,
                    'sequence_year' => $year,
                ]);
            }

            if ($rule->reset_annually && $rule->sequence_year !== $year) {
                $rule->forceFill(['next_number' => 1, 'sequence_year' => $year]);
                $rule->save();
            }

            $project = Project::findOrFail($projectId);
            for ($attempt = 0; $attempt < 100; $attempt++) {
                $sequence = str_pad((string) $rule->next_number, $rule->padding, '0', STR_PAD_LEFT);
                $reference = strtr($rule->pattern, [
                    '{project}' => strtoupper(trim($project->code ?: (string) $project->id)),
                    '{type}' => strtoupper($type),
                    '{subtype}' => strtoupper($subtype),
                    '{yy}' => substr((string) $year, -2),
                    '{yyyy}' => (string) $year,
                    '{sequence}' => $sequence,
                ]);

                $rule->increment('next_number');

                if (! ProjectCorrespondence::withTrashed()
                    ->where('project_id', $projectId)
                    ->where('reference_no', $reference)
                    ->exists()) {
                    return $reference;
                }

                $rule->refresh();
            }

            throw new RuntimeException('Unable to allocate a unique correspondence reference number.');
        });
    }

    private function referenceType(string $type, ?string $subtype): ?string
    {
        $subtype = strtoupper(trim((string) $subtype));
        if (in_array($subtype, ['MA', 'MS', 'DWG', 'REPORT', 'ADMIN', 'SUBCON'], true)) {
            return $subtype;
        }

        $type = strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', trim($type)));

        return [
            'RFI' => 'RFI',
            'RFWI' => 'RFWI',
            'SITE_MEMO' => 'SITE_MEMO',
            'EI' => 'EI',
            'PTW' => 'PTW',
            'DRAWING' => 'DWG',
            'REPORT' => 'REPORT',
            'SUBCON' => 'SUBCON',
            'ADMIN' => 'ADMIN',
        ][$type] ?? null;
    }
}
