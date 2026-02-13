<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceService $attendanceService,
        private PayrollService $payrollService,
    ) {}

    // ── Attendance Upload ──

    /**
     * Upload an Excel file with attendance data.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $result = $this->attendanceService->importFromExcel(
            $request->file('file'),
            $request->user()->id
        );

        $hasErrors = !empty($result['errors']);
        $message = "{$result['imported']} records imported successfully.";
        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} rows skipped.";
        }

        return $this->success([
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'batch' => $result['batch'],
            'errors' => $result['errors'],
        ], $message, $hasErrors ? 207 : 200);
    }

    /**
     * List attendance records with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $records = $this->attendanceService->listRecords(
            $request->only(['user_id', 'date', 'date_from', 'date_to', 'status', 'batch']),
            $request->integer('per_page', 15)
        );

        return $this->success($records);
    }

    /**
     * Show a single attendance record.
     */
    public function show(int $id): JsonResponse
    {
        $record = $this->attendanceService->getRecord($id);

        return $this->success($record);
    }

    /**
     * Get attendance summary stats for a period.
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $summary = $this->attendanceService->getSummary(
            $request->date_from,
            $request->date_to
        );

        return $this->success($summary);
    }

    /**
     * Get upload history (batches).
     */
    public function uploadHistory(Request $request): JsonResponse
    {
        $history = $this->attendanceService->getUploadHistory(
            $request->integer('per_page', 10)
        );

        return $this->success($history);
    }

    /**
     * Delete all records from a specific import batch.
     */
    public function deleteBatch(string $batch): JsonResponse
    {
        $deleted = $this->attendanceService->deleteBatch($batch);

        return $this->success(
            ['deleted' => $deleted],
            "{$deleted} attendance records deleted."
        );
    }

    // ── Payroll ──

    /**
     * Generate payroll from attendance for a period.
     */
    public function generatePayroll(Request $request): JsonResponse
    {
        $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
        ]);

        $result = $this->payrollService->generatePayroll(
            $request->period_start,
            $request->period_end,
            $request->user()->id,
            $request->filled('base_salary') ? (float) $request->base_salary : null
        );

        if ($result['generated'] === 0 && !empty($result['errors'])) {
            return $this->error($result['errors'][0], 422);
        }

        $message = "{$result['generated']} payroll records generated.";
        if (!empty($result['errors'])) {
            $message .= ' Some records had errors.';
        }

        return $this->success($result, $message);
    }

    /**
     * List payroll records.
     */
    public function payrollIndex(Request $request): JsonResponse
    {
        $records = $this->payrollService->listRecords(
            $request->only(['user_id', 'period_start', 'period_end', 'status']),
            $request->integer('per_page', 15)
        );

        return $this->success($records);
    }

    /**
     * Show a single payroll record.
     */
    public function payrollShow(int $id): JsonResponse
    {
        $record = $this->payrollService->getRecord($id);

        return $this->success($record);
    }

    /**
     * Approve a draft payroll record.
     */
    public function payrollApprove(int $id, Request $request): JsonResponse
    {
        try {
            $record = $this->payrollService->approve($id, $request->user()->id);
            return $this->success($record, 'Payroll record approved.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Mark a payroll record as paid.
     */
    public function payrollMarkPaid(int $id): JsonResponse
    {
        try {
            $record = $this->payrollService->markPaid($id);
            return $this->success($record, 'Payroll record marked as paid.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Get payroll summary for a period.
     */
    public function payrollSummary(Request $request): JsonResponse
    {
        $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $summary = $this->payrollService->getPeriodSummary(
            $request->period_start,
            $request->period_end
        );

        return $this->success($summary);
    }

    /**
     * Get current payroll configuration.
     */
    public function payrollConfig(): JsonResponse
    {
        return $this->success([
            'working_hours_per_day' => config('payroll.working_hours_per_day'),
            'overtime_multiplier' => config('payroll.overtime_multiplier'),
            'late_threshold_minutes' => config('payroll.late_threshold_minutes'),
            'half_day_hours' => config('payroll.half_day_hours'),
            'default_shift_start' => config('payroll.default_shift_start'),
            'default_shift_end' => config('payroll.default_shift_end'),
            'default_base_salary' => config('payroll.default_base_salary'),
            'working_days_per_month' => config('payroll.working_days_per_month'),
            'currency' => config('payroll.currency'),
            'deduct_absences' => config('payroll.deduct_absences'),
        ]);
    }
}
