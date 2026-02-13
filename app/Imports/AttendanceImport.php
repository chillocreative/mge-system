<?php

namespace App\Imports;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

class AttendanceImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    private string $uploadBatch;
    private int $uploadedBy;
    private array $rowErrors = [];
    private int $importedCount = 0;
    private int $skippedCount = 0;
    private array $userCache = [];

    public function __construct(int $uploadedBy)
    {
        $this->uploadedBy = $uploadedBy;
        $this->uploadBatch = Str::uuid()->toString();
    }

    public function collection(Collection $rows): void
    {
        $cols = config('payroll.excel_columns');
        $workingHoursPerDay = config('payroll.working_hours_per_day');
        $halfDayHours = config('payroll.half_day_hours');
        $lateThreshold = config('payroll.late_threshold_minutes');
        $shiftStart = config('payroll.default_shift_start');

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 for heading row + 0-index

            try {
                $employeeId = $this->cleanValue($row[$cols['employee_id']] ?? null);
                $dateRaw = $this->cleanValue($row[$cols['date']] ?? null);
                $clockInRaw = $this->cleanValue($row[$cols['clock_in']] ?? null);
                $clockOutRaw = $this->cleanValue($row[$cols['clock_out']] ?? null);

                // Skip completely empty rows
                if (!$employeeId && !$dateRaw) {
                    continue;
                }

                // Validate employee exists
                $user = $this->resolveUser($employeeId);
                if (!$user) {
                    $this->rowErrors[] = [
                        'row' => $rowNumber,
                        'field' => 'employee_id',
                        'value' => $employeeId,
                        'message' => "Employee ID \"{$employeeId}\" not found.",
                    ];
                    $this->skippedCount++;
                    continue;
                }

                // Parse date
                $date = $this->parseDate($dateRaw);
                if (!$date) {
                    $this->rowErrors[] = [
                        'row' => $rowNumber,
                        'field' => 'date',
                        'value' => $dateRaw,
                        'message' => "Invalid date format \"{$dateRaw}\". Expected YYYY-MM-DD or DD/MM/YYYY.",
                    ];
                    $this->skippedCount++;
                    continue;
                }

                // Parse clock in/out
                $clockIn = $this->parseDateTime($date, $clockInRaw);
                $clockOut = $this->parseDateTime($date, $clockOutRaw);

                if ($clockInRaw && !$clockIn) {
                    $this->rowErrors[] = [
                        'row' => $rowNumber,
                        'field' => 'clock_in',
                        'value' => $clockInRaw,
                        'message' => "Invalid clock-in time \"{$clockInRaw}\".",
                    ];
                    $this->skippedCount++;
                    continue;
                }

                // Calculate hours
                $workingHours = 0;
                $overtimeHours = 0;

                if ($clockIn && $clockOut) {
                    $totalMinutes = $clockIn->diffInMinutes($clockOut);
                    $workingHours = round($totalMinutes / 60, 2);

                    if ($workingHours > $workingHoursPerDay) {
                        $overtimeHours = round($workingHours - $workingHoursPerDay, 2);
                        $workingHours = $workingHoursPerDay;
                    }
                }

                // Determine status
                $status = 'present';
                if (!$clockIn && !$clockOut) {
                    $status = 'absent';
                    $workingHours = 0;
                } elseif (($workingHours + $overtimeHours) < $halfDayHours) {
                    $status = 'half_day';
                } elseif ($clockIn) {
                    $expectedStart = Carbon::parse($date->format('Y-m-d') . ' ' . $shiftStart);
                    if ($clockIn->diffInMinutes($expectedStart, false) < -$lateThreshold) {
                        $status = 'late';
                    }
                }

                // Upsert attendance record (unique on user_id + date)
                AttendanceRecord::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'date' => $date->format('Y-m-d'),
                    ],
                    [
                        'clock_in' => $clockIn,
                        'clock_out' => $clockOut,
                        'working_hours' => $workingHours,
                        'overtime_hours' => $overtimeHours,
                        'status' => $status,
                        'source' => 'excel_import',
                        'upload_batch' => $this->uploadBatch,
                        'uploaded_by' => $this->uploadedBy,
                    ]
                );

                $this->importedCount++;
            } catch (\Throwable $e) {
                $this->rowErrors[] = [
                    'row' => $rowNumber,
                    'field' => 'general',
                    'value' => null,
                    'message' => "Unexpected error: {$e->getMessage()}",
                ];
                $this->skippedCount++;
            }
        }
    }

    public function rules(): array
    {
        return [
            // Loose validation — detailed checks happen in collection()
            '*.employee_id' => 'present',
            '*.date' => 'present',
        ];
    }

    // ── Helpers ──

    private function resolveUser(mixed $employeeId): ?User
    {
        if (!$employeeId) {
            return null;
        }

        $key = (string) $employeeId;

        if (isset($this->userCache[$key])) {
            return $this->userCache[$key];
        }

        // Try by ID first, then by email
        $user = is_numeric($employeeId)
            ? User::find((int) $employeeId)
            : User::where('email', $employeeId)->first();

        $this->userCache[$key] = $user;

        return $user;
    }

    private function parseDate(mixed $raw): ?Carbon
    {
        if (!$raw) {
            return null;
        }

        // Excel serial date number
        if (is_numeric($raw) && (int) $raw > 40000) {
            try {
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((int) $raw));
            } catch (\Throwable) {
                // Fall through to string parsing
            }
        }

        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d'];
        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, (string) $raw)->startOfDay();
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function parseDateTime(?Carbon $date, mixed $raw): ?Carbon
    {
        if (!$raw || !$date) {
            return null;
        }

        // Excel fractional day (0.375 = 09:00)
        if (is_numeric($raw) && (float) $raw < 1) {
            $totalMinutes = round((float) $raw * 24 * 60);
            $hours = intdiv((int) $totalMinutes, 60);
            $minutes = $totalMinutes % 60;
            return $date->copy()->setTime($hours, (int) $minutes);
        }

        // Excel serial datetime
        if (is_numeric($raw) && (float) $raw > 40000) {
            try {
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $raw));
            } catch (\Throwable) {
                // Fall through
            }
        }

        $timeFormats = ['H:i:s', 'H:i', 'g:i A', 'g:iA', 'g:i a', 'g:ia'];
        foreach ($timeFormats as $format) {
            try {
                $time = Carbon::createFromFormat($format, trim((string) $raw));
                return $date->copy()->setTime($time->hour, $time->minute, $time->second);
            } catch (\Throwable) {
                continue;
            }
        }

        // Full datetime string
        try {
            return Carbon::parse((string) $raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private function cleanValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            return trim($value);
        }
        return $value;
    }

    // ── Result accessors ──

    public function getUploadBatch(): string
    {
        return $this->uploadBatch;
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function getRowErrors(): array
    {
        return $this->rowErrors;
    }
}
