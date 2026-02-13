<?php

namespace App\Services;

use App\Imports\AttendanceImport;
use App\Models\AttendanceRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceService
{
    /**
     * Import attendance from an uploaded Excel file.
     *
     * @return array{imported: int, skipped: int, batch: string, errors: array}
     */
    public function importFromExcel(UploadedFile $file, int $uploadedBy): array
    {
        $import = new AttendanceImport($uploadedBy);

        Excel::import($import, $file);

        // Collect validation failures from WithValidation + SkipsOnFailure
        $validationErrors = [];
        foreach ($import->failures() as $failure) {
            $validationErrors[] = [
                'row' => $failure->row(),
                'field' => $failure->attribute(),
                'value' => implode(', ', $failure->values()),
                'message' => implode(' ', $failure->errors()),
            ];
        }

        return [
            'imported' => $import->getImportedCount(),
            'skipped' => $import->getSkippedCount() + count($validationErrors),
            'batch' => $import->getUploadBatch(),
            'errors' => array_merge($import->getRowErrors(), $validationErrors),
        ];
    }

    /**
     * List attendance records with filters.
     */
    public function listRecords(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = AttendanceRecord::with('user:id,first_name,last_name,email,department_id')
            ->with('user.department:id,name');

        if (!empty($filters['user_id'])) {
            $query->forUser((int) $filters['user_id']);
        }

        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->forPeriod($filters['date_from'], $filters['date_to']);
        } elseif (!empty($filters['date'])) {
            $query->whereDate('date', $filters['date']);
        }

        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        if (!empty($filters['batch'])) {
            $query->byBatch($filters['batch']);
        }

        return $query->latest('date')->paginate($perPage);
    }

    /**
     * Get a single attendance record.
     */
    public function getRecord(int $id): AttendanceRecord
    {
        return AttendanceRecord::with(['user:id,first_name,last_name,email', 'uploader:id,first_name,last_name'])
            ->findOrFail($id);
    }

    /**
     * Delete all records from a specific import batch.
     */
    public function deleteBatch(string $batch): int
    {
        return AttendanceRecord::byBatch($batch)->delete();
    }

    /**
     * Get attendance summary stats for a period.
     */
    public function getSummary(string $dateFrom, string $dateTo): array
    {
        return AttendanceRecord::query()
            ->forPeriod($dateFrom, $dateTo)
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present")
            ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent")
            ->selectRaw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late")
            ->selectRaw("SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END) as half_day")
            ->selectRaw('COALESCE(SUM(working_hours), 0) as total_working_hours')
            ->selectRaw('COALESCE(SUM(overtime_hours), 0) as total_overtime_hours')
            ->first()
            ->toArray();
    }

    /**
     * Get unique import batches with metadata.
     */
    public function getUploadHistory(int $perPage = 10): LengthAwarePaginator
    {
        return AttendanceRecord::query()
            ->select('upload_batch')
            ->selectRaw('MIN(date) as period_start')
            ->selectRaw('MAX(date) as period_end')
            ->selectRaw('COUNT(*) as record_count')
            ->selectRaw('COUNT(DISTINCT user_id) as employee_count')
            ->selectRaw('MIN(created_at) as uploaded_at')
            ->selectRaw('MAX(uploaded_by) as uploaded_by')
            ->whereNotNull('upload_batch')
            ->groupBy('upload_batch')
            ->orderByDesc('uploaded_at')
            ->paginate($perPage);
    }
}
