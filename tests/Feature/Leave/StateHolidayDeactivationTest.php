<?php

namespace Tests\Feature\Leave;

use App\Models\PublicHoliday;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The migration that switches the leave calendar to national holidays only.
 */
class StateHolidayDeactivationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_09_07_170001_deactivate_state_public_holidays.php');
    }

    public function test_it_deactivates_state_holidays_but_leaves_national_ones(): void
    {
        $state = PublicHoliday::create([
            'name' => 'George Town World Heritage City Day', 'date' => '2026-07-07',
            'year' => 2026, 'scope' => 'state', 'state' => 'Penang', 'is_active' => true,
        ]);
        $national = PublicHoliday::create([
            'name' => 'Malaysia Day', 'date' => '2026-09-16',
            'year' => 2026, 'scope' => 'national', 'is_active' => true,
        ]);

        $this->migration()->up();

        $this->assertFalse($state->fresh()->is_active, 'State holiday should be deactivated.');
        $this->assertTrue($national->fresh()->is_active, 'National holiday must be untouched.');
    }

    public function test_deactivated_state_holiday_is_kept_not_deleted(): void
    {
        $state = PublicHoliday::create([
            'name' => 'George Town World Heritage City Day', 'date' => '2026-07-07',
            'year' => 2026, 'scope' => 'state', 'state' => 'Penang', 'is_active' => true,
        ]);

        $this->migration()->up();

        // Reversible: the row survives so HR can turn it back on if they choose.
        $this->assertDatabaseHas('public_holidays', ['id' => $state->id, 'is_active' => false]);

        $this->migration()->down();
        $this->assertTrue($state->fresh()->is_active);
    }
}
