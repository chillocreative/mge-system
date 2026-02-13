<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\PayrollRecord;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class FinanceService
{
    /**
     * Monthly financial summary for a given period.
     */
    public function getMonthlySummary(string $year, string $month): array
    {
        $from = "{$year}-{$month}-01";
        $to = date('Y-m-t', strtotime($from));

        // Revenue (invoices)
        $invoiceStats = Invoice::query()
            ->whereBetween('issue_date', [$from, $to])
            ->selectRaw('COUNT(*) as total_invoices')
            ->selectRaw('COALESCE(SUM(total), 0) as total_invoiced')
            ->selectRaw('COALESCE(SUM(amount_paid), 0) as total_collected')
            ->selectRaw('COALESCE(SUM(balance_due), 0) as total_outstanding')
            ->selectRaw("SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count")
            ->selectRaw("SUM(CASE WHEN status IN ('sent','partially_paid') THEN 1 ELSE 0 END) as unpaid_count")
            ->selectRaw("SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('paid','cancelled') THEN 1 ELSE 0 END) as overdue_count")
            ->first()
            ->toArray();

        // Expenses
        $expenseStats = Expense::query()
            ->whereBetween('expense_date', [$from, $to])
            ->selectRaw('COUNT(*) as total_expenses')
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END), 0) as approved_amount")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) as pending_amount")
            ->first()
            ->toArray();

        // Expense breakdown by category
        $expenseByCategory = Expense::query()
            ->whereBetween('expense_date', [$from, $to])
            ->byStatus('approved')
            ->select('category')
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->toArray();

        // Payroll
        $payrollStats = PayrollRecord::query()
            ->where('period_start', '>=', $from)
            ->where('period_end', '<=', $to)
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw('COALESCE(SUM(net_salary), 0) as total_payroll')
            ->selectRaw('COALESCE(SUM(overtime_pay), 0) as total_overtime')
            ->selectRaw('COALESCE(SUM(deductions), 0) as total_deductions')
            ->selectRaw("SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count")
            ->selectRaw("SUM(CASE WHEN status != 'paid' THEN 1 ELSE 0 END) as unpaid_count")
            ->first()
            ->toArray();

        $totalIncome = (float) $invoiceStats['total_collected'];
        $totalExpenses = (float) $expenseStats['approved_amount'] + (float) $payrollStats['total_payroll'];

        return [
            'period' => ['year' => $year, 'month' => $month, 'from' => $from, 'to' => $to],
            'invoices' => $invoiceStats,
            'expenses' => $expenseStats,
            'expense_by_category' => $expenseByCategory,
            'payroll' => $payrollStats,
            'summary' => [
                'total_income' => $totalIncome,
                'total_expenses' => $totalExpenses,
                'net_profit' => round($totalIncome - $totalExpenses, 2),
                'profit_margin' => $totalIncome > 0 ? round((($totalIncome - $totalExpenses) / $totalIncome) * 100, 2) : 0,
            ],
        ];
    }

    /**
     * Budget vs Actual comparison across all active projects.
     */
    public function getBudgetVsActual(): array
    {
        $projects = Project::query()
            ->select('id', 'name', 'code', 'budget', 'spent', 'status')
            ->where('budget', '>', 0)
            ->orderByDesc('budget')
            ->get();

        $result = [];
        foreach ($projects as $project) {
            $invoiceRevenue = Invoice::forProject($project->id)
                ->whereNotIn('status', ['cancelled', 'draft'])
                ->sum('total');

            $approvedExpenses = Expense::forProject($project->id)
                ->byStatus('approved')
                ->sum('amount');

            $variance = round((float) $project->budget - $approvedExpenses, 2);
            $utilizationPercent = $project->budget > 0
                ? round(($approvedExpenses / (float) $project->budget) * 100, 2)
                : 0;

            $result[] = [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'project_code' => $project->code,
                'status' => $project->status,
                'budget' => (float) $project->budget,
                'actual_spent' => $approvedExpenses,
                'invoiced_revenue' => $invoiceRevenue,
                'variance' => $variance,
                'utilization_percent' => $utilizationPercent,
                'over_budget' => $variance < 0,
            ];
        }

        // Totals
        $totals = [
            'total_budget' => array_sum(array_column($result, 'budget')),
            'total_spent' => array_sum(array_column($result, 'actual_spent')),
            'total_revenue' => array_sum(array_column($result, 'invoiced_revenue')),
            'total_variance' => array_sum(array_column($result, 'variance')),
        ];

        return ['projects' => $result, 'totals' => $totals];
    }

    /**
     * Finance overview stats for the dashboard.
     */
    public function getOverviewStats(): array
    {
        $thisMonth = now()->startOfMonth()->toDateString();
        $thisMonthEnd = now()->endOfMonth()->toDateString();

        return [
            'total_receivables' => Invoice::whereNotIn('status', ['paid', 'cancelled'])->sum('balance_due'),
            'total_revenue_this_month' => Invoice::whereBetween('issue_date', [$thisMonth, $thisMonthEnd])->sum('amount_paid'),
            'overdue_invoices' => Invoice::overdue()->count(),
            'pending_expenses' => Expense::byStatus('pending')->count(),
            'total_expenses_this_month' => Expense::byStatus('approved')->whereBetween('expense_date', [$thisMonth, $thisMonthEnd])->sum('amount'),
            'total_payroll_pending' => PayrollRecord::whereIn('status', ['draft', 'approved'])->sum('net_salary'),
        ];
    }
}
