<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Services\Leave\LeaveEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Shadow-mode balance comparison — plan 27.14 stage P3.
 *
 * The plan calls this the most valuable step in the whole go-live, and it is
 * right: the engine calculates every employee's balance while the existing
 * numbers stay in use, so discrepancies surface before anyone's leave depends on
 * them. It replaces the manual "pick 10 employees and check by hand" exercise of
 * plan 27.6 with an automatic check across everybody.
 *
 * Default mode is --shadow: nothing is written. --commit writes the balances,
 * and is the step to take only after the differences have been reviewed and
 * understood — plan 27.6 point 4 is emphatic that each difference must be
 * *understood*, not just reconciled. A difference where the engine is right and
 * the old number was wrong is a good find; one where the engine is wrong needs
 * fixing before it reaches an employee.
 */
class RecalculateLeaveBalances extends Command
{
    protected $signature = 'leave:recalculate
        {--year= : Leave year to calculate (defaults to the current year)}
        {--employee= : Restrict to a single employee id}
        {--commit : Write the calculated balances. Without this, nothing is saved}
        {--threshold=0.01 : Ignore differences smaller than this}';

    protected $description = 'Recalculate leave balances and report differences against the stored ones (shadow mode by default)';

    public function handle(LeaveEngine $engine): int
    {
        $year = (int) ($this->option('year') ?: now()->year);
        $commit = (bool) $this->option('commit');
        $threshold = (float) $this->option('threshold');

        $types = LeaveType::active()->get();

        if ($types->isEmpty()) {
            $this->error('No active leave types. Nothing to calculate.');

            return self::FAILURE;
        }

        $employees = Employee::query()
            ->when($this->option('employee'), fn ($q, $id) => $q->where('id', $id))
            ->orderBy('employee_no')
            ->get();

        if ($employees->isEmpty()) {
            $this->error('No employees found.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%s balances for %d employee(s) x %d leave type(s), year %d.',
            $commit ? 'Recalculating and WRITING' : 'Recalculating in SHADOW MODE (nothing will be written):',
            $employees->count(),
            $types->count(),
            $year,
        ));
        $this->newLine();

        $differences = [];
        $locked = 0;
        $unchanged = 0;

        foreach ($employees as $employee) {
            foreach ($types as $type) {
                $stored = LeaveBalance::where('employee_id', $employee->id)
                    ->where('leave_type_id', $type->id)
                    ->where('year', $year)
                    ->first();

                if ($stored?->is_locked) {
                    $locked++;

                    continue;
                }

                $summary = $engine->summary($employee, $type, $year);

                $storedEntitled = $stored ? (float) $stored->entitled_days : null;
                $storedRemaining = $stored ? (float) $stored->remaining_days : null;

                $entitledDiff = $storedEntitled === null ? null : round($summary['entitled'] - $storedEntitled, 2);
                $remainingDiff = $storedRemaining === null ? null : round($summary['available'] - $storedRemaining, 2);

                $changed = $stored === null
                    || abs((float) $entitledDiff) >= $threshold
                    || abs((float) $remainingDiff) >= $threshold;

                if (! $changed) {
                    $unchanged++;

                    continue;
                }

                $differences[] = [
                    'employee' => $employee->employee_no.' — '.trim($employee->first_name.' '.$employee->last_name),
                    'type' => $type->code,
                    'entitled_was' => $storedEntitled === null ? '—' : number_format($storedEntitled, 1),
                    'entitled_now' => number_format($summary['entitled'], 1),
                    'remaining_was' => $storedRemaining === null ? '—' : number_format($storedRemaining, 1),
                    'remaining_now' => number_format($summary['available'], 1),
                ];
            }
        }

        if ($commit) {
            DB::transaction(function () use ($engine, $employees, $types, $year) {
                foreach ($employees as $employee) {
                    foreach ($types as $type) {
                        $engine->syncBalance($employee, $type, $year);
                    }
                }
            });
        }

        $this->renderReport($differences, $unchanged, $locked, $commit);
        $this->warnAboutMissingServiceDates($employees);

        return self::SUCCESS;
    }

    /**
     * Warn about employees whose length of service cannot be determined.
     *
     * Without a hire date the resolver returns zero years, which puts the
     * employee in the lowest tier with no proration. That is the safe default —
     * it never awards the most generous tier by accident — but it is silently
     * wrong for anyone with real service behind them: someone six years in gets
     * the under-two-years entitlement and nothing anywhere says so.
     *
     * It is a data-entry gap, fixed by HR on the staff screen rather than in
     * code. Printing it here means it cannot be missed, because this is the
     * command you run before deciding whether the engine is safe to switch on.
     *
     * @param  \Illuminate\Support\Collection<int, Employee>  $employees
     */
    private function warnAboutMissingServiceDates($employees): void
    {
        $missing = $employees->filter(fn (Employee $e) => $e->hire_date === null);

        if ($missing->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->warn(sprintf(
            '⚠  %d of %d employee(s) have no hire date, so their length of service is unknown.',
            $missing->count(),
            $employees->count(),
        ));
        $this->line('   They fall into the lowest entitlement tier with no proration. Anyone with');
        $this->line('   real service is therefore under-granted, and nothing on screen says so.');
        $this->newLine();

        foreach ($missing as $employee) {
            $this->line('   • '.$employee->employee_no.' — '.trim($employee->first_name.' '.$employee->last_name));
        }

        $this->newLine();
        $this->comment('   Fix in HR > Staff > Edit before enabling the engine.');
    }

    /**
     * @param  array<int, array<string, string>>  $differences
     */
    private function renderReport(array $differences, int $unchanged, int $locked, bool $commit): void
    {
        if ($differences === []) {
            $this->info('No differences. Every calculated balance matches what is stored.');
        } else {
            $this->warn(count($differences).' difference(s) found:');
            $this->newLine();
            $this->table(
                ['Employee', 'Type', 'Entitled was', 'now', 'Remaining was', 'now'],
                array_map('array_values', $differences),
            );
        }

        $this->newLine();
        $this->line("Matched:   {$unchanged}");
        $this->line('Differing: '.count($differences));

        if ($locked > 0) {
            $this->line("Locked:    {$locked} (skipped — closed leave years are never recalculated)");
        }

        $this->newLine();

        if ($commit) {
            $this->info('Balances written.');

            return;
        }

        $this->comment('Shadow mode: nothing was written.');
        $this->comment('Review every difference above before running with --commit.');
        $this->comment('A difference where the engine is right and the old number was wrong is a good find.');
        $this->comment('A difference where the engine is wrong must be fixed before any employee sees it.');
    }
}
