<?php

namespace App\Http\Controllers\Api\ReportData;

use App\Http\Controllers\Controller;
use App\Services\ContractService;
use App\Support\RinggitWords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractParticularsController extends Controller
{
    public function __construct(private ContractService $contracts) {}

    public function show(int $projectId): JsonResponse
    {
        $contract = $this->contracts->mainContract($projectId);

        return $this->success([
            'contract' => $contract,
            'contract_sum_words' => $contract->contract_sum !== null ? RinggitWords::spell($contract->contract_sum) : null,
            'performance_bond_words' => $contract->performance_bond_amount !== null ? RinggitWords::spell($contract->performance_bond_amount) : null,
        ]);
    }

    public function update(int $projectId, Request $request): JsonResponse
    {
        $contract = $this->contracts->mainContract($projectId);

        $validated = $request->validate([
            'contract_sum' => ['nullable', 'numeric', 'min:0'],
            'performance_bond_amount' => ['nullable', 'numeric', 'min:0'],
            'duration_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'dlp_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'lad_per_day' => ['nullable', 'numeric', 'min:0'],
            'possession_date' => ['nullable', 'date'],
            'completion_date' => ['nullable', 'date'],
            'dlp_start_date' => ['nullable', 'date'],
            'dlp_end_date' => ['nullable', 'date'],
            'cidb_registration' => ['nullable', 'string', 'max:255'],
            'insurances' => ['nullable', 'array', 'max:10'],
            'insurances.*.type' => ['required', 'string', 'max:255'],
            'insurances.*.insurer' => ['nullable', 'string', 'max:255'],
            'insurances.*.policy_no' => ['nullable', 'string', 'max:255'],
            'insurances.*.period_from' => ['nullable', 'date'],
            'insurances.*.period_to' => ['nullable', 'date'],
            'insurances.*.maintenance_from' => ['nullable', 'date'],
            'insurances.*.maintenance_to' => ['nullable', 'date'],
        ]);

        $contract->update($validated);

        return $this->show($projectId);
    }
}
