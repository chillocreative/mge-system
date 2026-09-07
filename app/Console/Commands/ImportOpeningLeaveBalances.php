<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Loads opening leave balances — days already taken before the system went live
 * (plan 27.4, option A).
 *
 * The module went live in September; staff took leave from January to August
 * that the system never recorded. Without loading it, everyone appears to have
 * their full entitlement, leave gets approved on numbers that are too high, and
 * the correction later means clawing back approvals.
 *
 * How it lands: the engine derives "days taken" from leave_days rows, which do
 * not exist for pre-system leave, so setting used_days would have no effect on
 * the computed balance. Instead this records the already-taken days as a
 * negative adjustment_days on the balance row — the lever the calculator
 * actually reads (available = entitled + carried + adjustment − taken − pending).
 *
 * CSV format (header row required):
 *   employee_no,leave_code,days_taken
 *   MGE01,AL,4
 *   MGE01,MC,2
 *
 * Dry run by default — prints what it would do and writes nothing. Pass --commit
 * to apply. Idempotent: the adjustment is SET to −days_taken (not incremented),
 * so re-running with a corrected file simply overwrites, never stacks.
 */
class ImportOpeningLeaveBalances extends Command
{
    protected $signature = 'leave:opening-balance
        {file : Path to the CSV of days already taken this year}
        {--year= : Leave year the figures apply to (default: current year)}
        {--commit : Write the adjustments. Without this nothing is saved}';

    protected $description = 'Load opening leave balances (days already taken before go-live) from a CSV';

    public function handle(): int
    {
        $path = $this->argument('file');
        $year = (int) ($this->option('year') ?: now()->year);
        $commit = (bool) $this->option('commit');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $rows = array_map('str_getcsv', file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        $header = array_map(fn ($h) => strtolower(trim($h)), array_shift($rows) ?? []);

        foreach (['employee_no', 'leave_code', 'days_taken'] as $col) {
            if (! in_array($col, $header, true)) {
                $this->error("CSV must have a '{$col}' column. Found: ".implode(', ', $header));

                return self::FAILURE;
            }
        }

        $types = LeaveType::pluck('id', 'code');
        $planned = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2; // header is line 1
            $data = array_combine($header, array_pad($row, count($header), null));

            $employee = Employee::where('employee_no', trim((string) $data['employee_no']))->first();
            $code = strtoupper(trim((string) $data['leave_code']));
            $days = (float) $data['days_taken'];

            if (! $employee) {
                $errors[] = "Line {$line}: no employee with number '{$data['employee_no']}'";

                continue;
            }
            if (! isset($types[$code])) {
                $errors[] = "Line {$line}: unknown leave code '{$code}'";

                continue;
            }
            if ($days < 0) {
                $errors[] = "Line {$line}: days_taken cannot be negative";

                continue;
            }

            $planned[] = [
                'employee' => $employee,
                'leave_type_id' => $types[$code],
                'code' => $code,
                'days' => $days,
            ];
        }

        if ($errors) {
            $this->error(count($errors).' problem(s) found — nothing was written:');
            foreach ($errors as $e) {
                $this->line('  '.$e);
            }

            return self::FAILURE;
        }

        $this->table(
            ['Employee', 'Type', 'Days already taken', 'Adjustment'],
            array_map(fn ($p) => [
                $p['employee']->employee_no.' — '.trim($p['employee']->first_name.' '.$p['employee']->last_name),
                $p['code'],
                number_format($p['days'], 1),
                number_format(-$p['days'], 1),
            ], $planned),
        );

        if (! $commit) {
            $this->newLine();
            $this->comment('Dry run — nothing written. Re-run with --commit to apply.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($planned, $year) {
            foreach ($planned as $p) {
                $balance = LeaveBalance::firstOrNew([
                    'employee_id' => $p['employee']->id,
                    'leave_type_id' => $p['leave_type_id'],
                    'year' => $year,
                ]);

                // SET (not increment) so re-running with a corrected file overwrites.
                $balance->adjustment_days = -$p['days'];
                $balance->entitled_days ??= 0;
                $balance->used_days ??= 0;
                $balance->remaining_days ??= 0;
                $balance->save();
            }
        });

        $this->info(count($planned).' opening balance(s) written for '.$year.'.');
        $this->comment('Run `php artisan leave:recalculate --year='.$year.' --commit` to refresh the stored snapshots.');

        return self::SUCCESS;
    }
}
