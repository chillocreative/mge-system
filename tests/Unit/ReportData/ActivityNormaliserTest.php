<?php

namespace Tests\Unit\ReportData;

use App\Services\ReportData\Programme\ActivityNormaliser;
use PHPUnit\Framework\TestCase;

class ActivityNormaliserTest extends TestCase
{
    // --- date() ---

    public function test_date_parses_excel_serial(): void
    {
        // 45,000 is within the 20000-80000 window (2023-03-15-ish).
        $this->assertSame('2023-03-15', ActivityNormaliser::date(45000));
    }

    public function test_date_ignores_numeric_outside_serial_window(): void
    {
        $this->assertNull(ActivityNormaliser::date(5));
        $this->assertNull(ActivityNormaliser::date(90000));
    }

    public function test_date_parses_d_m_y_slash(): void
    {
        $this->assertSame('2026-01-31', ActivityNormaliser::date('31/01/2026'));
    }

    public function test_date_parses_d_m_y_two_digit_year(): void
    {
        $this->assertSame('2026-01-31', ActivityNormaliser::date('31/01/26'));
    }

    public function test_date_parses_y_m_d(): void
    {
        $this->assertSame('2026-01-31', ActivityNormaliser::date('2026-01-31'));
    }

    public function test_date_parses_d_m_y(): void
    {
        $this->assertSame('2026-01-31', ActivityNormaliser::date('31-Jan-26'));
    }

    public function test_date_parses_d_m_y_with_spaces(): void
    {
        $this->assertSame('2026-01-31', ActivityNormaliser::date('31 Jan 2026'));
    }

    public function test_date_parses_iso8601_datetime(): void
    {
        $this->assertSame('2026-01-31', ActivityNormaliser::date('2026-01-31T08:00:00'));
    }

    public function test_date_returns_null_for_garbage(): void
    {
        $this->assertNull(ActivityNormaliser::date('not a date'));
        $this->assertNull(ActivityNormaliser::date(null));
        $this->assertNull(ActivityNormaliser::date(''));
    }

    // --- percent() ---

    public function test_percent_strips_percent_sign(): void
    {
        $this->assertSame(45.0, ActivityNormaliser::percent('45%', false));
    }

    public function test_percent_converts_comma_to_dot(): void
    {
        $this->assertSame(45.5, ActivityNormaliser::percent('45,5', false));
    }

    public function test_percent_fraction_mode_multiplies_by_100(): void
    {
        $this->assertSame(45.0, ActivityNormaliser::percent(0.45, true));
    }

    public function test_percent_clamps_to_0_100(): void
    {
        $this->assertSame(100.0, ActivityNormaliser::percent(150, false));
        $this->assertSame(0.0, ActivityNormaliser::percent(-10, false));
    }

    public function test_percent_returns_null_for_non_numeric(): void
    {
        $this->assertNull(ActivityNormaliser::percent('n/a', false));
        $this->assertNull(ActivityNormaliser::percent(null, false));
    }

    // --- durationDays() ---

    public function test_duration_days_plain_number(): void
    {
        $this->assertSame(12, ActivityNormaliser::durationDays(12));
        $this->assertSame(12, ActivityNormaliser::durationDays('12'));
    }

    public function test_duration_days_with_days_suffix(): void
    {
        $this->assertSame(12, ActivityNormaliser::durationDays('12 days'));
        $this->assertSame(12, ActivityNormaliser::durationDays('12d'));
    }

    public function test_duration_days_weeks_converted_to_days(): void
    {
        $this->assertSame(8, ActivityNormaliser::durationDays('1.5 wks'));
    }

    public function test_duration_days_iso8601_mspdi(): void
    {
        $this->assertSame(5, ActivityNormaliser::durationDays('PT40H0M0S'));
        $this->assertSame(12, ActivityNormaliser::durationDays('PT96H0M0S'));
    }

    public function test_duration_days_zero_allowed(): void
    {
        $this->assertSame(0, ActivityNormaliser::durationDays('0'));
        $this->assertSame(0, ActivityNormaliser::durationDays(0));
    }

    public function test_duration_days_returns_null_for_garbage(): void
    {
        $this->assertNull(ActivityNormaliser::durationDays('not a duration'));
        $this->assertNull(ActivityNormaliser::durationDays(null));
    }

    // --- outlineFromIndent() / inferIndentUnit() ---

    public function test_outline_from_indent_top_level(): void
    {
        $result = ActivityNormaliser::outlineFromIndent('Mobilization', 2);
        $this->assertSame(['level' => 1, 'name' => 'Mobilization'], $result);
    }

    public function test_outline_from_indent_nested_levels(): void
    {
        $this->assertSame(['level' => 2, 'name' => 'Excavation'], ActivityNormaliser::outlineFromIndent('  Excavation', 2));
        $this->assertSame(['level' => 3, 'name' => 'Formwork'], ActivityNormaliser::outlineFromIndent('    Formwork', 2));
    }

    public function test_outline_from_indent_tab_counts_as_unit(): void
    {
        $this->assertSame(['level' => 2, 'name' => 'Excavation'], ActivityNormaliser::outlineFromIndent("\tExcavation", 4));
    }

    public function test_infer_indent_unit_uses_smallest_nonzero(): void
    {
        $names = ['Mobilization', '  Excavation', '    Formwork', '   Other'];
        $this->assertSame(2, ActivityNormaliser::inferIndentUnit($names));
    }

    public function test_infer_indent_unit_defaults_to_2_when_no_indentation(): void
    {
        $this->assertSame(2, ActivityNormaliser::inferIndentUnit(['Mobilization', 'Excavation']));
    }

    // --- deriveSummaries() ---

    public function test_derive_summaries_flags_rows_with_deeper_next_row(): void
    {
        $activities = [
            ['name' => 'A', 'outline_level' => 1, 'is_summary' => false],
            ['name' => 'B', 'outline_level' => 2, 'is_summary' => false],
            ['name' => 'C', 'outline_level' => 2, 'is_summary' => false],
            ['name' => 'D', 'outline_level' => 1, 'is_summary' => false],
        ];

        $result = ActivityNormaliser::deriveSummaries($activities);

        $this->assertTrue($result[0]['is_summary']);
        $this->assertFalse($result[1]['is_summary']);
        $this->assertFalse($result[2]['is_summary']);
        $this->assertFalse($result[3]['is_summary']);
    }
}
