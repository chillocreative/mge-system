<?php

namespace App\Services\Environment;

use App\Models\EnvironmentProjectSetting;
use App\Models\EnvironmentReport;
use App\Models\ProjectContract;
use App\Models\ProjectParty;
use App\Models\WaterQualityRecord;
use App\Support\RinggitWords;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EnvironmentSectionDefaults
{
    /**
     * Build the full `sections` object for a report.
     */
    public function build(EnvironmentReport $report): array
    {
        $sections = [];
        foreach (EnvironmentReport::SECTION_KEYS as $key) {
            $sections[$key] = $this->buildOne($report, $key);
        }

        return $sections;
    }

    /**
     * Build a single section by key.
     */
    public function buildOne(EnvironmentReport $report, string $key): array
    {
        return match ($key) {
            'contract' => $this->contract($report),
            'ems' => $this->ems($report),
            'introduction' => $this->introduction($report),
            'flow_chart' => $this->flowChart($report),
            'policy' => $this->policy($report),
            'location' => $this->location($report),
            'parameters' => $this->parameters($report),
            'results' => $this->results($report),
            'bmp' => $this->bmp($report),
            default => [],
        };
    }

    /**
     * Default signatories for a freshly created report.
     */
    public function signatories(EnvironmentReport $report): array
    {
        $project = $report->project;
        $settings = EnvironmentProjectSetting::where('project_id', $project->id)->first();
        $parties = $this->parties($project->id);
        $contractor = $this->party($parties, 'contractor');
        $contractorContact = $contractor?->contacts->first();

        $prepared = $contractorContact
            ? [
                'slot' => 'prepared',
                'name' => $contractorContact->name ?? '',
                'designation' => $contractorContact->designation ?? '',
                'company' => $contractor?->name ?? '',
            ]
            : [
                'slot' => 'prepared',
                'name' => $settings?->officer_name ?? '',
                'designation' => 'Environment Officer',
                'company' => $settings?->consultant_company ?? ($contractor?->name ?? ''),
            ];

        return [
            $prepared,
            [
                'slot' => 'verified',
                'name' => $settings?->consultant_name ?? '',
                'designation' => 'Environment Consultant',
                'company' => $settings?->consultant_company ?? '',
            ],
            ['slot' => 'accepted', 'name' => '', 'designation' => '', 'company' => ''],
        ];
    }

    // ── Section builders ──

    private function contract(EnvironmentReport $report): array
    {
        $project = $report->project;
        $c = $this->mainContract($project->id);
        $d = fn ($v) => $v ? Carbon::parse($v)->format('d/m/Y') : null;

        $rows = [
            ['label' => 'Project Title', 'value' => $project->name],
            ['label' => 'Contract Number', 'value' => $c?->contract_no ?? 'TBA'],
            ['label' => 'Contract Sum', 'value' => $c?->contract_sum !== null
                ? 'RM '.number_format((float) $c->contract_sum, 2).' ('.RinggitWords::spell($c->contract_sum).')'
                : 'TBA'],
            ['label' => 'Performance Guarantee Fund / Wang Jaminan Perlaksanaan (WJP)', 'value' => $c?->performance_bond_amount !== null
                ? 'RM '.number_format((float) $c->performance_bond_amount, 2).' ('.RinggitWords::spell($c->performance_bond_amount).')'
                : 'TBA'],
            ['label' => 'Duration of Completion', 'value' => $c?->duration_months ? $c->duration_months.' MONTHS' : 'TBA'],
            ['label' => 'Contract Period', 'value' => 'COMPLETION: '.($c?->duration_months ? $c->duration_months.' MONTHS' : 'TBA').' | DLP: '.($c?->dlp_months ? $c->dlp_months.' MONTHS' : 'TBA')],
            ['label' => 'Date Contract', 'value' => 'Possession Date: '.($d($c?->possession_date) ?? 'TBA')
                .' | Completion Date: '.($d($c?->completion_date) ?? 'TBA')
                .' | DLP Start: '.($d($c?->dlp_start_date) ?? 'TBA')
                .' | DLP Completion: '.($d($c?->dlp_end_date) ?? 'TBA')],
            ['label' => 'CIDB Registration (Form CIDB L2/96)', 'value' => $c?->cidb_registration ?: 'TBA'],
            ['label' => 'Insurance', 'value' => $this->insuranceLine($c?->insurances ?? [])],
        ];

        return ['rows' => $rows];
    }

    private function insuranceLine(array $insurances): string
    {
        if ($insurances === []) {
            return 'TBA';
        }

        $i = $insurances[0];
        $d = fn ($v) => $v ? Carbon::parse($v)->format('d/m/Y') : 'TBA';
        $type = $i['type'] ?? "Contractor's All Risk";

        return $type.': '.($i['insurer'] ?? 'TBA')
            .' | Policy Number: '.($i['policy_no'] ?? 'TBA')
            .' | Period of Insurance: '.$d($i['period_from'] ?? null).' - '.$d($i['period_to'] ?? null)
            .' | Maintenance Period: '.$d($i['maintenance_from'] ?? null).' - '.$d($i['maintenance_to'] ?? null);
    }

    private function ems(EnvironmentReport $report): array
    {
        $settings = EnvironmentProjectSetting::where('project_id', $report->project_id)->first();

        $consultantCompany = $settings?->consultant_company ?? 'TBA';
        $consultantName = $settings?->consultant_name ?? 'TBA';

        return [
            'consultant_company' => $consultantCompany,
            'consultant_name' => $consultantName,
            'consultant_reg_no' => $settings?->consultant_reg_no ?? 'TBA',
            'officer_name' => $settings?->officer_name ?? 'TBA',
            'officer_reg_no' => $settings?->officer_reg_no ?? 'TBA',
            'certified_text' => "The Department of Environment (DOE) Consultant Registration Scheme certifies {$consultantName}, an individual employed by {$consultantCompany}, as a registered consultant, as stated in the Notification of Consultant Registration.",
        ];
    }

    private function introduction(EnvironmentReport $report): array
    {
        $project = $report->project;
        $c = $this->mainContract($project->id);

        $duration = $c?->duration_months ? "{$c->duration_months} months" : 'TBA';
        $start = $c?->start_date ?? $project->start_date;
        $end = $c?->end_date ?? $project->end_date;
        $startLabel = $start ? Carbon::parse($start)->format('jS F Y') : 'TBA';
        $endLabel = $end ? Carbon::parse($end)->format('jS F Y') : 'TBA';

        $contractor = $this->party($this->parties($project->id), 'contractor');
        $contractorName = $contractor?->name ?? 'TBA';

        $text = 'The Monthly Environmental Monitoring Report documents the implementation of environmental management '
            ."and monitoring measures for the project \"{$project->name}\" for the reporting period of {$duration}. "
            ."The overall project duration is from {$startLabel} to {$endLabel}.\n\n"
            ."Prepared in accordance with the Environmental Policy of {$contractorName}, the report reflects proactive "
            .'environmental management, pollution prevention, continual improvement, and consideration of community and '
            .'environmental concerns. Environmental protection is integrated into project design, construction planning, '
            .'and site implementation.';

        return ['text' => $text];
    }

    private function flowChart(EnvironmentReport $report): array
    {
        $project = $report->project;
        $settings = EnvironmentProjectSetting::where('project_id', $project->id)->first();
        $parties = $this->parties($project->id);
        $owner = $this->party($parties, 'owner');
        $so = $this->party($parties, 'superintending_officer');
        $contractor = $this->party($parties, 'contractor');

        $proponentLines = array_values(array_filter([$owner?->name, $owner?->role_label]));
        $mainContractorLines = array_values(array_filter([$contractor?->name, $contractor?->contacts->first()?->name]));

        return [
            'proponent' => ['title' => 'Project Proponent', 'lines' => $proponentLines ?: ['TBA']],
            'implementer' => ['title' => 'Project Implementer', 'lines' => $so?->name ? [$so->name] : ['TBA']],
            'main_contractor' => ['title' => 'Main Contractor', 'lines' => $mainContractorLines ?: ['TBA']],
            'env_officer' => ['title' => 'Environmental Officer', 'lines' => [$settings?->officer_name ?? 'TBA']],
            'env_consultant' => ['title' => 'Environmental Consultant', 'lines' => [$settings?->consultant_company ?? 'TBA']],
            'plans' => ['Environmental Management Plan (EMP)', 'Erosion & Sediment Control Plan (ESCP)'],
        ];
    }

    private function policy(EnvironmentReport $report): array
    {
        return ['source' => 'project', 'note' => ''];
    }

    private function location(EnvironmentReport $report): array
    {
        return ['source' => 'project', 'note' => ''];
    }

    private function parameters(EnvironmentReport $report): array
    {
        $project = $report->project;
        $c = $this->mainContract($project->id);
        $start = $c?->start_date ?? $project->start_date;

        $months = $this->monthRange($start, $report->period_end);

        $periods = [];
        $no = 1;
        foreach ($months as $month) {
            $hasWater = WaterQualityRecord::forProject($project->id)
                ->whereYear('sample_date', $month->year)
                ->whereMonth('sample_date', $month->month)
                ->exists();

            $periods[] = [
                'no' => $no++,
                'session' => $month->format('F Y'),
                'water' => $hasWater ? '✓' : '-',
                'air' => '-',
                'noise' => '-',
                'vibration' => '-',
            ];
        }

        return [
            'water' => [
                'Temperature °C',
                'pH',
                'Dissolved Oxygen mg/L',
                'COD, mg/L',
                'BOD 5 days @ 20°C, mg/L',
                'Total Suspended Solids, mg/L',
                'Oil & Grease, mg/L',
                'E. Coli, CFU/100',
                'Ammoniacal Nitrogen, mg/L',
                'Salinity, ppt',
            ],
            'air' => [
                'Total Suspended Particulate (TSP)',
                'Particulate Matter (PM10)',
                'Wind Velocity and direction',
            ],
            'noise' => ['Noise Level'],
            'vibration' => ['Vibration Level'],
            'periods' => $periods,
        ];
    }

    private function results(EnvironmentReport $report): array
    {
        $record = WaterQualityRecord::forProject($report->project_id)
            ->whereDate('sample_date', '>=', $report->period_start)
            ->whereDate('sample_date', '<=', $report->period_end)
            ->orderByDesc('sample_date')
            ->orderByDesc('id')
            ->first();

        $insitu = $record?->insitu ?? [];
        $lab = $record?->lab ?? [];

        $points = WaterQualityRecord::POINTS;
        $valueAt = fn (array $source, string $point, string $field) => $source[$point][$field] ?? null;

        $definitions = [
            ['label' => 'Temperature (In-situ)', 'source' => $insitu, 'field' => 'temperature'],
            ['label' => 'Temperature (Lab)', 'source' => $lab, 'field' => 'temperature'],
            ['label' => 'pH (In-situ)', 'source' => $insitu, 'field' => 'ph'],
            ['label' => 'pH (Lab)', 'source' => $lab, 'field' => 'ph'],
            ['label' => 'Dissolved Oxygen (In-situ)', 'source' => $insitu, 'field' => 'do'],
            ['label' => 'Dissolved Oxygen (Lab)', 'source' => $lab, 'field' => 'do'],
            ['label' => 'Chemical Oxygen Demand (COD)', 'source' => $lab, 'field' => 'cod'],
            ['label' => 'Biological Oxygen Demand (BOD)', 'source' => $lab, 'field' => 'bod'],
            ['label' => 'Total Suspended Solid (TSS)', 'source' => $lab, 'field' => 'tss'],
            ['label' => 'Oil & Grease', 'source' => $lab, 'field' => 'oil_grease'],
            ['label' => 'E-coli', 'source' => $lab, 'field' => 'ecoli'],
            ['label' => 'Ammoniacal Nitrogen', 'source' => $lab, 'field' => 'ammoniacal_n'],
        ];

        $rows = [];
        foreach ($definitions as $def) {
            $row = ['parameter' => $def['label'], 'nwqs' => '-', 'doe' => '-'];
            foreach ($points as $point) {
                $value = $valueAt($def['source'], $point, $def['field']);
                $row[strtolower($point)] = ($value === null || $value === '') ? '-' : $value;
            }
            $rows[] = $row;
        }

        return [
            'columns' => ['NWQS', 'DOE', 'W1', 'W2', 'W3', 'W4'],
            'rows' => $rows,
        ];
    }

    private function bmp(EnvironmentReport $report): array
    {
        return [
            'intro' => 'These are BMPs that have been constructed/installed:',
            'items' => [
                ['no' => 1, 'item' => 'Wash Trough', 'installed' => true],
                ['no' => 2, 'item' => 'Water Browser Truck', 'installed' => true],
                ['no' => 3, 'item' => 'Silt Trap', 'installed' => true],
                ['no' => 4, 'item' => 'Scheduled Waste Storage', 'installed' => true],
            ],
        ];
    }

    // ── Helpers ──

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

    /**
     * Every calendar month from $start (inclusive) up to $end (inclusive).
     *
     * @return Carbon[]
     */
    private function monthRange($start, $end): array
    {
        if (! $start || ! $end) {
            return [];
        }

        $cursor = Carbon::parse($start)->startOfMonth();
        $last = Carbon::parse($end)->startOfMonth();

        if ($cursor->gt($last)) {
            return [$cursor];
        }

        $months = [];
        while ($cursor->lte($last)) {
            $months[] = $cursor->copy();
            $cursor->addMonth();
        }

        return $months;
    }
}
