<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayrollRecord;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(private PayrollService $payrollService) {}

    /**
     * Adjust allowances/bonus/PCB/zakat + manual deductions and recalculate.
     */
    public function recalculate(Request $request, int $payroll): JsonResponse
    {
        $validated = $request->validate([
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'pcb' => ['nullable', 'numeric', 'min:0'],
            'zakat' => ['nullable', 'numeric', 'min:0'],
            'deduction_items' => ['nullable', 'array'],
            'deduction_items.*.type' => ['required_with:deduction_items', 'in:penalty,cash_advance,personal_loan,other'],
            'deduction_items.*.description' => ['nullable', 'string', 'max:255'],
            'deduction_items.*.amount' => ['required_with:deduction_items', 'numeric', 'min:0'],
        ]);

        return $this->success(
            $this->payrollService->recalculate($payroll, $validated),
            'Payslip recalculated.'
        );
    }

    /**
     * Stream the payslip PDF.
     */
    public function payslip(int $payroll)
    {
        return $this->payrollService->generatePayslipPdf($payroll)
            ->stream("payslip-{$payroll}.pdf");
    }

    /**
     * Email the payslip to the recipient.
     */
    public function emailPayslip(int $payroll): JsonResponse
    {
        try {
            $record = $this->payrollService->emailPayslip($payroll);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($record, 'Payslip emailed successfully.');
    }

    /**
     * Email payslips for an entire period (all records in the period).
     */
    public function batchEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
        ]);

        $records = PayrollRecord::forPeriod($validated['period_start'], $validated['period_end'])->get();

        $sent = 0;
        $errors = [];
        foreach ($records as $record) {
            try {
                $this->payrollService->emailPayslip($record->id);
                $sent++;
            } catch (\Throwable $e) {
                $errors[] = "Payslip #{$record->id}: {$e->getMessage()}";
            }
        }

        return $this->success(['sent' => $sent, 'errors' => $errors], "{$sent} payslip(s) emailed.");
    }

    /**
     * Generate the annual EA Form (C.P.8A) PDF for an employee.
     */
    public function eaForm(int $employee, int $year)
    {
        return $this->payrollService->generateEaForm($employee, $year)
            ->stream("ea-form-{$employee}-{$year}.pdf");
    }
}
