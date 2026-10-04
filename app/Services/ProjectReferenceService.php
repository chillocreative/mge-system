<?php

namespace App\Services;

use App\Models\ProjectCorrespondence;
use App\Models\ProjectReferenceAllocation;
use App\Models\ProjectReferenceSequence;
use App\Models\ProjectReferenceSetting;
use App\Models\ProjectReferenceTemplate;
use App\Repositories\Contracts\ProjectReferenceRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectReferenceService
{
    public function __construct(private readonly ProjectReferenceRepositoryInterface $repository) {}

    public function configuration(int $projectId): array
    {
        $this->ensureFoundation($projectId);

        return [
            'settings' => $this->repository->settingForProject($projectId),
            'templates' => $this->repository->templatesForProject($projectId),
            'supported_tokens' => [
                '{company}', '{client}', '{project}', '{alternate_project}', '{volume}',
                '{type}', '{yy}', '{yyyy}', '{mm}', '{mmyy}', '{sequence}',
            ],
        ];
    }

    public function updateConfiguration(int $projectId, array $data): array
    {
        DB::transaction(function () use ($projectId, $data) {
            $this->ensureFoundation($projectId);
            ProjectReferenceSetting::where('project_id', $projectId)->update($data['settings']);
            foreach ($data['templates'] as $template) {
                ProjectReferenceTemplate::where('project_id', $projectId)
                    ->where('code', strtoupper($template['code']))
                    ->update([
                        'name' => $template['name'],
                        'type_token' => $template['type_token'],
                        'pattern' => $template['pattern'],
                        'padding' => $template['padding'],
                        'reset_period' => $template['reset_period'],
                        'is_active' => $template['is_active'],
                    ]);
            }
        });

        return $this->configuration($projectId);
    }

    public function preview(int $projectId, string $code, ?CarbonInterface $date = null): array
    {
        $this->ensureFoundation($projectId);
        $date ??= now();
        $template = $this->repository->templateForProject($projectId, $code);
        $settings = $this->repository->settingForProject($projectId);
        $periodKey = $this->periodKey($template, $date);
        $next = (int) (ProjectReferenceSequence::where('project_reference_template_id', $template->id)
            ->where('period_key', $periodKey)->value('next_number') ?? 1);

        [$reference, $sequence] = $this->nextAvailable($projectId, $template, $settings, $date, $periodKey, $next);

        return ['reference_no' => $reference, 'sequence_number' => $sequence, 'period_key' => $periodKey];
    }

    public function generate(int $projectId, string $code, ?CarbonInterface $date = null, ?int $userId = null): ProjectReferenceAllocation
    {
        $this->ensureFoundation($projectId);
        $date ??= now();

        return DB::transaction(function () use ($projectId, $code, $date, $userId) {
            $template = $this->repository->templateForProject($projectId, $code, true);
            $settings = $this->repository->settingForProject($projectId);
            $periodKey = $this->periodKey($template, $date);
            $sequence = ProjectReferenceSequence::firstOrCreate(
                ['project_reference_template_id' => $template->id, 'period_key' => $periodKey],
                ['next_number' => 1],
            );
            $sequence->refresh();

            [$reference, $number] = $this->nextAvailable(
                $projectId, $template, $settings, $date, $periodKey, $sequence->next_number,
            );
            $allocation = ProjectReferenceAllocation::create([
                'project_id' => $projectId,
                'project_reference_template_id' => $template->id,
                'period_key' => $periodKey,
                'sequence_number' => $number,
                'reference_no' => $reference,
                'generated_by' => $userId,
            ]);
            $sequence->update(['next_number' => $number + 1]);

            return $allocation->load('template');
        }, 3);
    }

    public function ensureFoundation(int $projectId): void
    {
        ProjectReferenceSetting::firstOrCreate(['project_id' => $projectId], [
            'company_code' => 'MGE',
            'client_code' => 'JPS',
            'primary_project_code' => 'TGOLAK',
            'alternate_project_code' => 'OLAK',
            'volume_code' => 'VOL1',
        ]);

        foreach (self::defaultTemplates() as $template) {
            ProjectReferenceTemplate::firstOrCreate(
                ['project_id' => $projectId, 'code' => $template['code']],
                $template,
            );
        }
    }

    public static function defaultTemplates(): array
    {
        $standard = '{company}/{client}-{project}/{type}/{yy}-{sequence}';
        $volumed = '{company}/{alternate_project}/{type}/{volume}/{mmyy}/{sequence}';
        $site = '{company}/{project}/{type}/{yy}-{sequence}';

        return collect([
            ['code' => 'MA', 'name' => 'Material Approval', 'type_token' => 'MA', 'pattern' => $standard],
            ['code' => 'MS', 'name' => 'Method Statement', 'type_token' => 'MS', 'pattern' => $standard],
            ['code' => 'DWG', 'name' => 'Drawing', 'type_token' => 'DWG', 'pattern' => $standard],
            ['code' => 'REPORT', 'name' => 'Report', 'type_token' => 'DATA', 'pattern' => $standard],
            ['code' => 'ADMIN', 'name' => 'Admin', 'type_token' => 'ADMIN', 'pattern' => $standard],
            ['code' => 'SUBCON', 'name' => 'Subcontractor', 'type_token' => 'SUBCON', 'pattern' => $standard],
            ['code' => 'RFI', 'name' => 'Request for Information', 'type_token' => 'RFI', 'pattern' => $volumed],
            ['code' => 'RFWI', 'name' => 'Request for Work Inspection', 'type_token' => 'RFWI', 'pattern' => $volumed],
            ['code' => 'SITE_MEMO', 'name' => 'Site Memo', 'type_token' => 'SM', 'pattern' => $site],
            ['code' => 'EI', 'name' => 'Engineer Instruction', 'type_token' => 'EI', 'pattern' => $site],
            ['code' => 'PTW', 'name' => 'Permit to Work', 'type_token' => 'PTW', 'pattern' => $site],
        ])->map(fn (array $template) => $template + [
            'padding' => 3,
            'reset_period' => 'annual',
            'is_active' => true,
        ])->all();
    }

    private function nextAvailable(
        int $projectId,
        ProjectReferenceTemplate $template,
        ProjectReferenceSetting $settings,
        CarbonInterface $date,
        string $periodKey,
        int $startingNumber,
    ): array {
        for ($number = $startingNumber; $number < $startingNumber + 1000; $number++) {
            $reference = $this->render($template, $settings, $date, $number);
            $allocated = ProjectReferenceAllocation::where('project_id', $projectId)
                ->where('reference_no', $reference)->exists();
            $usedByCorrespondence = ProjectCorrespondence::withTrashed()
                ->where('project_id', $projectId)->where('reference_no', $reference)->exists();
            if (! $allocated && ! $usedByCorrespondence) {
                return [$reference, $number];
            }
        }

        throw ValidationException::withMessages(['reference' => 'Unable to find an available reference number.']);
    }

    private function render(
        ProjectReferenceTemplate $template,
        ProjectReferenceSetting $settings,
        CarbonInterface $date,
        int $number,
    ): string {
        return strtr($template->pattern, [
            '{company}' => strtoupper($settings->company_code),
            '{client}' => strtoupper($settings->client_code),
            '{project}' => strtoupper($settings->primary_project_code),
            '{alternate_project}' => strtoupper($settings->alternate_project_code),
            '{volume}' => strtoupper($settings->volume_code),
            '{type}' => strtoupper($template->type_token),
            '{yy}' => $date->format('y'),
            '{yyyy}' => $date->format('Y'),
            '{mm}' => $date->format('m'),
            '{mmyy}' => $date->format('my'),
            '{sequence}' => str_pad((string) $number, $template->padding, '0', STR_PAD_LEFT),
        ]);
    }

    private function periodKey(ProjectReferenceTemplate $template, CarbonInterface $date): string
    {
        return match ($template->reset_period) {
            'monthly' => $date->format('Ym'),
            'never' => 'all',
            default => $date->format('Y'),
        };
    }
}
