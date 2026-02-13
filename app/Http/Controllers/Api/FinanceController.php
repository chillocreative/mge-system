<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function __construct(private FinanceService $financeService) {}

    public function overview(): JsonResponse
    {
        return $this->success($this->financeService->getOverviewStats());
    }

    public function monthlySummary(Request $request): JsonResponse
    {
        $request->validate([
            'year' => ['required', 'digits:4'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $summary = $this->financeService->getMonthlySummary(
            $request->year,
            str_pad($request->month, 2, '0', STR_PAD_LEFT)
        );

        return $this->success($summary);
    }

    public function budgetVsActual(): JsonResponse
    {
        return $this->success($this->financeService->getBudgetVsActual());
    }
}
