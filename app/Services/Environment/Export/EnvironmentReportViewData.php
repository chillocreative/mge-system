<?php

namespace App\Services\Environment\Export;

use App\Models\EnvironmentReport;
use App\Models\EnvironmentReportAsset;
use App\Models\ProjectContract;
use App\Models\ProjectParty;
use App\Services\Environment\EnvironmentSectionDefaults;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the shared view-data payload for the Environment Report PDF and DOCX exporters, so
 * both build from a single, tested source (mirrors MonthlyReport's ReportViewData).
 */
class EnvironmentReportViewData
{
    private const IMAGE_MIME = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'];

    /** Section numbers as they appear in the printed report (TOC + section headings). */
    public const SECTION_NUMBERS = [
        'contract' => '1.0',
        'ems' => '2.0',
        'introduction' => '3.0',
        'flow_chart' => '4.0',
        'policy' => '5.0',
        'location' => '6.0',
        'parameters' => '7.0',
        'results' => '8.0',
        'bmp' => '9.0',
    ];

    public const SECTION_TITLES = [
        'contract' => 'CONTRACT PARTICULAR',
        'ems' => 'THE ENVIRONMENT MANAGEMENT SYSTEM',
        'introduction' => 'INTRODUCTION',
        'flow_chart' => 'PROJECT IMPLEMENTATION FLOW CHART',
        'policy' => 'ENVIRONMENT POLICY',
        'location' => 'MONITORING LOCATION',
        'parameters' => 'QUALITY REFERENCES',
        'results' => 'Monitoring Result',
        'bmp' => 'BEST MANAGEMENT PRACTICE',
    ];

    /** @var array<string, array<int, array{num: string, title: string}>> Sub-items shown under a section heading and in the TOC. */
    public const SECTION_SUBITEMS = [
        'contract' => [['num' => '1.1', 'title' => 'Contract Information']],
        'ems' => [['num' => '2.1', 'title' => 'Certified Environment Consultant']],
        'parameters' => [
            ['num' => '7.1', 'title' => 'Test Parameters'],
            ['num' => '7.1', 'title' => 'Monitoring Period'],
        ],
    ];

    public function __construct(private EnvironmentSectionDefaults $defaults) {}

    public static function build(EnvironmentReport $report): array
    {
        return app(self::class)->viewData($report);
    }

    private function viewData(EnvironmentReport $report): array
    {
        $report->loadMissing(['project', 'assets']);
        $project = $report->project;
        $contract = $this->mainContract($project->id);
        $parties = $this->parties($project->id);

        $owner = $this->party($parties, 'owner');
        $so = $this->party($parties, 'superintending_officer');
        $consultant = $this->party($parties, 'consultant');
        $contractor = $this->party($parties, 'contractor');

        $partyData = fn (?ProjectParty $party) => [
            'name' => $party?->name ?? '',
            'address' => $party?->address ?? '',
            'address_lines' => $this->addressLines($party?->address),
            'logo' => $this->dataUriFromDisk($party?->logo_path),
        ];

        $sections = $this->sections($report);

        $periodStart = $report->period_start ? Carbon::parse($report->period_start) : null;
        $periodEnd = $report->period_end ? Carbon::parse($report->period_end) : null;
        $periodLabel = ($periodStart && $periodEnd)
            ? strtoupper($periodStart->format('d M Y')).'– '.strtoupper($periodEnd->format('d M Y'))
            : '';

        $settings = \App\Models\EnvironmentProjectSetting::where('project_id', $project->id)->first();

        return [
            'report' => $report,
            'project' => $project,
            'contract' => $contract,
            'contract_no' => $contract?->contract_no ?? 'TBA',
            'period_label' => $periodLabel,
            'report_title' => $report->title,
            'report_no' => $report->report_no,
            'logo_mge' => $this->mgeLogo(),
            'logo_so' => $this->dataUriFromDisk($so?->logo_path) ?? $this->dataUriFromDisk($owner?->logo_path),
            'so_name' => $so?->name ?? '',
            'parties' => [
                'owner' => $partyData($owner),
                'so' => $partyData($so),
                'consultant' => $partyData($consultant),
                'contractor' => $partyData($contractor),
            ],
            'signatories' => $this->signatories($report),
            'sections' => $sections,
            'section_numbers' => self::SECTION_NUMBERS,
            'section_titles' => self::SECTION_TITLES,
            'section_subitems' => self::SECTION_SUBITEMS,
            'policy_image' => $this->overridableImage($report, $sections['policy'] ?? [], 'policy_override', $settings?->policy_image_path),
            'location_image' => $this->overridableImage($report, $sections['location'] ?? [], 'location_override', $settings?->location_map_path),
            'bmp_photos' => $this->assetImages($report, 'bmp_photo'),
            'consultant_cert' => $this->firstAssetImage($report, 'consultant_cert'),
        ];
    }

    /** Report sections merged with generated fallbacks so blades never see a missing key. */
    private function sections(EnvironmentReport $report): array
    {
        $sections = $report->sections ?? [];

        foreach (EnvironmentReport::SECTION_KEYS as $key) {
            if (empty($sections[$key])) {
                $sections[$key] = $this->defaults->buildOne($report, $key);
            }
        }

        return $sections;
    }

    /** Always-present 3-slot signatory list (prepared/verified/accepted), never null fields. */
    private function signatories(EnvironmentReport $report): array
    {
        $stored = collect($report->signatories ?? []);
        $result = [];

        foreach (['prepared', 'verified', 'accepted'] as $slot) {
            $sig = $stored->firstWhere('slot', $slot) ?? [];
            $result[$slot] = [
                'slot' => $slot,
                'name' => $sig['name'] ?? '',
                'designation' => $sig['designation'] ?? '',
                'company' => $sig['company'] ?? '',
            ];
        }

        return $result;
    }

    /**
     * Resolves an image that may be overridden by a report asset, falling back to the
     * project-setting default. Returns ['data_uri' => ?string, 'pdf_path' => ?string].
     */
    private function overridableImage(EnvironmentReport $report, array $section, string $overrideKind, ?string $settingPath): array
    {
        $useOverride = ($section['source'] ?? 'project') === 'report';

        $path = null;
        if ($useOverride) {
            $asset = $report->assets->firstWhere('kind', $overrideKind);
            if ($asset) {
                $path = $asset->file_path;
            }
        }

        if ($path === null) {
            $path = $settingPath;
        }

        if (! $path) {
            return ['data_uri' => null, 'pdf_path' => null];
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $disk = Storage::disk('local');

        if ($ext === 'pdf') {
            return ['data_uri' => null, 'pdf_path' => $path];
        }

        if (! isset(self::IMAGE_MIME[$ext]) || ! $disk->exists($path)) {
            return ['data_uri' => null, 'pdf_path' => null];
        }

        return ['data_uri' => 'data:'.self::IMAGE_MIME[$ext].';base64,'.base64_encode($disk->get($path)), 'pdf_path' => null];
    }

    /** @return array<int, array{data_uri: ?string, caption: ?string}> */
    private function assetImages(EnvironmentReport $report, string $kind): array
    {
        return $report->assets->where('kind', $kind)->values()->map(function (EnvironmentReportAsset $asset) {
            return ['data_uri' => $this->assetDataUri($asset), 'caption' => $asset->caption];
        })->filter(fn ($item) => $item['data_uri'] !== null)->values()->all();
    }

    private function firstAssetImage(EnvironmentReport $report, string $kind): ?string
    {
        $asset = $report->assets->firstWhere('kind', $kind);

        return $asset ? $this->assetDataUri($asset) : null;
    }

    private function assetDataUri(EnvironmentReportAsset $asset): ?string
    {
        $ext = strtolower(pathinfo($asset->file_path, PATHINFO_EXTENSION));
        $mime = self::IMAGE_MIME[$ext] ?? null;
        if (! $mime || ! Storage::disk('local')->exists($asset->file_path)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($asset->file_path));
    }

    private function addressLines(?string $address): array
    {
        if (! $address) {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $address))));
    }

    private function mainContract(int $projectId): ?ProjectContract
    {
        return ProjectContract::where('project_id', $projectId)->where('is_main', true)->first();
    }

    private function parties(int $projectId): Collection
    {
        return ProjectParty::where('project_id', $projectId)
            ->whereNotNull('report_role')
            ->with('contacts')
            ->orderBy('sort_order')
            ->get();
    }

    private function party(Collection $parties, string $role): ?ProjectParty
    {
        return $parties->firstWhere('report_role', $role);
    }

    private function dataUriFromDisk(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = self::IMAGE_MIME[$ext] ?? null;
        if (! $mime) {
            return null;
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
    }

    private function mgeLogo(): ?string
    {
        $path = public_path('logo.png');
        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(file_get_contents($path));
    }
}
