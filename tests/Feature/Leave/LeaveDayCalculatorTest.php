<?php

namespace Tests\Feature\Leave;

use App\Models\Employee;
use App\Models\PublicHoliday;
use App\Models\WorkPattern;
use App\Services\Leave\HolidayCalendar;
use App\Services\Leave\LeaveDayCalculator;
use App\Services\Leave\WorkPatternResolver;
use Database\Seeders\LeavePolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Phase D — the leave day calculation engine.
 *
 * Every case in plan 27.7 ("Kes tepi yang mesti diuji") is a test here. Leave
 * arithmetic fails quietly: a wrong number looks exactly like a right one until
 * someone is refused leave they had, or granted leave they did not. These tests
 * are the only thing standing between that and production.
 */
class LeaveDayCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function calculator(): LeaveDayCalculator
    {
        return new LeaveDayCalculator(
            new WorkPatternResolver(WorkPattern::active()->get()),
            new HolidayCalendar,
        );
    }

    private function employee(string $category, string $no = 'E1'): Employee
    {
        return Employee::create([
            'employee_no' => $no,
            'first_name' => ucfirst($category),
            'category' => $category,
            'hire_date' => '2020-01-01',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LeavePolicySeeder::class);
    }

    // ── Rest days ──────────────────────────────────────────────────────────

    public function test_office_staff_do_not_lose_days_for_their_weekend(): void
    {
        $employee = $this->employee('office');

        // Mon 7 Sep 2026 – Fri 11 Sep 2026, plus the weekend either side.
        $result = $this->calculator()->calculate($employee, '2026-09-07', '2026-09-13');

        $this->assertSame(7, $result->calendarDays());
        $this->assertSame(5.0, $result->totalDeducted());

        $reasons = array_column($result->exclusions(), 'reason');
        $this->assertSame(['rest_day', 'rest_day'], $reasons);
    }

    public function test_site_staff_rest_only_on_sunday(): void
    {
        $employee = $this->employee('site');

        $result = $this->calculator()->calculate($employee, '2026-09-07', '2026-09-13');

        // Same seven days, but Saturday is a working day for site crews.
        $this->assertSame(6.0, $result->totalDeducted());
        $this->assertCount(1, $result->exclusions());
    }

    public function test_office_and_site_staff_are_deducted_differently_for_identical_dates(): void
    {
        $office = $this->employee('office', 'OFF-1');
        $site = $this->employee('site', 'SITE-1');

        $calculator = $this->calculator();
        $range = ['2026-09-07', '2026-09-13'];

        $officeDays = $calculator->calculate($office, ...$range)->totalDeducted();
        $siteDays = $calculator->calculate($site, ...$range)->totalDeducted();

        // This difference is correct, not a bug — plan 27.6b. It is surfaced on
        // the apply form so nobody has to guess why.
        $this->assertNotSame($officeDays, $siteDays);
        $this->assertSame(5.0, $officeDays);
        $this->assertSame(6.0, $siteDays);
    }

    public function test_an_explicit_employee_pattern_overrides_the_category_default(): void
    {
        $pattern = WorkPattern::create([
            'name' => 'Four-day week',
            'working_days' => [1, 2, 3, 4],
        ]);

        $employee = $this->employee('office', 'OVR-1');
        $employee->update(['work_pattern_id' => $pattern->id]);

        $result = $this->calculator()->calculate($employee->fresh(), '2026-09-07', '2026-09-13');

        // Mon–Thu only.
        $this->assertSame(4.0, $result->totalDeducted());
    }

    public function test_every_day_counts_when_no_pattern_is_configured(): void
    {
        WorkPattern::query()->delete();

        $employee = $this->employee('office', 'NOPAT-1');

        $result = $this->calculator()->calculate($employee, '2026-09-07', '2026-09-13');

        // Deliberate: with no pattern we over-deduct rather than under-deduct.
        // An over-deduction gets reported; a silent under-deduction is found at
        // year end, after the leave has been taken.
        $this->assertSame(7.0, $result->totalDeducted());
    }

    // ── Public holidays ────────────────────────────────────────────────────

    public function test_a_public_holiday_inside_the_range_is_not_deducted(): void
    {
        // Wed 16 Sep 2026 — Malaysia Day.
        PublicHoliday::create(['name' => 'Malaysia Day', 'date' => '2026-09-16', 'year' => 2026]);

        $employee = $this->employee('office');

        $result = $this->calculator()->calculate($employee, '2026-09-14', '2026-09-18');

        $this->assertSame(4.0, $result->totalDeducted());

        $exclusion = $result->exclusions()[0];
        $this->assertSame('public_holiday', $exclusion['reason']);
        $this->assertSame('Malaysia Day', $exclusion['label']);
    }

    public function test_a_holiday_falling_on_a_rest_day_is_recorded_as_a_rest_day(): void
    {
        // Sat 3 Oct 2026 — a rest day for office staff.
        PublicHoliday::create(['name' => 'Some Holiday', 'date' => '2026-10-03', 'year' => 2026]);

        $employee = $this->employee('office');

        $result = $this->calculator()->calculate($employee, '2026-10-02', '2026-10-05');

        // The employee was not working that day regardless, so the rest day is
        // the honest reason. It must not be double-counted as a bonus day.
        $saturday = collect($result->days)->firstWhere('date', '2026-10-03');
        $this->assertSame('rest_day', $saturday['exclusion_reason']);
        $this->assertFalse($saturday['is_deducted']);
    }

    public function test_holidays_gazetted_for_another_state_are_ignored(): void
    {
        PublicHoliday::create([
            'name' => 'Sarawak Day',
            'date' => '2026-09-15',
            'year' => 2026,
            'scope' => 'state',
            'state' => 'Sarawak',
        ]);

        $employee = $this->employee('office');

        $result = $this->calculator()->calculate($employee, '2026-09-14', '2026-09-18');

        // MGE is in Penang; a Sarawak holiday is not a day off here.
        $this->assertSame(5.0, $result->totalDeducted());
    }

    public function test_an_inactive_holiday_is_ignored(): void
    {
        PublicHoliday::create([
            'name' => 'Withdrawn Holiday',
            'date' => '2026-09-16',
            'year' => 2026,
            'is_active' => false,
        ]);

        $employee = $this->employee('office');

        $this->assertSame(
            5.0,
            $this->calculator()->calculate($employee, '2026-09-14', '2026-09-18')->totalDeducted(),
        );
    }

    // ── Half days ──────────────────────────────────────────────────────────

    public function test_a_half_day_deducts_one_half(): void
    {
        $employee = $this->employee('office');

        $result = $this->calculator()->calculate($employee, '2026-09-08', '2026-09-08', halfDay: true);

        $this->assertSame(0.5, $result->totalDeducted());
    }

    public function test_a_half_day_cannot_span_multiple_dates(): void
    {
        $employee = $this->employee('office');

        $this->expectException(InvalidArgumentException::class);
        $this->calculator()->calculate($employee, '2026-09-08', '2026-09-09', halfDay: true);
    }

    // ── Boundaries ─────────────────────────────────────────────────────────

    public function test_leave_crossing_a_year_boundary_splits_between_leave_years(): void
    {
        $employee = $this->employee('site'); // works Mon–Sat, so fewer rest days

        // Wed 30 Dec 2026 – Sat 2 Jan 2027.
        $result = $this->calculator()->calculate($employee, '2026-12-30', '2027-01-02');

        $this->assertTrue($result->spansMultipleYears());

        // Each year's balance takes only its own share (plan AB4).
        $this->assertSame([2026 => 2.0, 2027 => 2.0], $result->deductedByYear());
        $this->assertSame(4.0, $result->totalDeducted());
    }

    public function test_leave_crossing_a_month_stays_in_the_right_year(): void
    {
        $employee = $this->employee('site');

        $result = $this->calculator()->calculate($employee, '2026-01-29', '2026-02-03');

        $this->assertSame([2026], array_keys($result->deductedByYear()));
        $this->assertFalse($result->spansMultipleYears());
    }

    public function test_a_single_working_day_deducts_one_day(): void
    {
        $employee = $this->employee('office');

        $result = $this->calculator()->calculate($employee, '2026-09-08', '2026-09-08');

        $this->assertSame(1, $result->calendarDays());
        $this->assertSame(1.0, $result->totalDeducted());
    }

    public function test_a_range_entirely_within_rest_days_deducts_nothing(): void
    {
        $employee = $this->employee('office');

        // Sat 12 – Sun 13 Sep 2026.
        $result = $this->calculator()->calculate($employee, '2026-09-12', '2026-09-13');

        $this->assertSame(0.0, $result->totalDeducted());
        $this->assertSame(2, $result->calendarDays());
    }

    public function test_an_end_date_before_the_start_date_is_rejected(): void
    {
        $employee = $this->employee('office');

        $this->expectException(InvalidArgumentException::class);
        $this->calculator()->calculate($employee, '2026-09-10', '2026-09-08');
    }

    public function test_every_calendar_day_is_accounted_for(): void
    {
        $employee = $this->employee('office');

        $result = $this->calculator()->calculate($employee, '2026-09-01', '2026-09-30');

        // No day may be silently dropped: deducted + excluded must equal the range.
        $deducted = count(array_filter($result->days, fn ($d) => $d['is_deducted']));
        $this->assertSame(30, $deducted + count($result->exclusions()));
        $this->assertSame(30, $result->calendarDays());
    }
}
