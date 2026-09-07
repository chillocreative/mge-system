<?php

namespace Tests\Feature\Safety;

use App\Models\SafetyIncident;
use App\Models\SafetyManHour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SafetyStatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['safety.view', 'safety.manage'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
    }

    private function actor(array $perms): User
    {
        $u = User::create(['first_name' => 'S', 'last_name' => 'O', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->givePermissionTo($perms);

        return $u;
    }

    private function incident(array $over = []): void
    {
        SafetyIncident::create(array_merge([
            'reported_by' => $this->actor(['safety.view'])->id,
            'title' => 'X', 'description' => 'd', 'incident_date' => '2026-03-01',
            'severity' => 'minor', 'type' => 'other', 'status' => 'open',
        ], $over));
    }

    public function test_ltifr_and_severity_rate_are_computed_per_million_man_hours(): void
    {
        // 2 lost-time injuries (days_lost > 0), 8 days lost total, over 200,000 man-hours.
        $this->incident(['type' => 'injury', 'severity' => 'serious', 'days_lost' => 5]);
        $this->incident(['type' => 'injury', 'severity' => 'moderate', 'days_lost' => 3]);
        $this->incident(['type' => 'injury', 'severity' => 'minor', 'days_lost' => 0]); // recordable, not lost-time
        $this->incident(['type' => 'near_miss']);
        SafetyManHour::create(['year' => 2026, 'month' => 1, 'man_hours' => 100000]);
        SafetyManHour::create(['year' => 2026, 'month' => 2, 'man_hours' => 100000]);

        $res = $this->actingAs($this->actor(['safety.view']))
            ->getJson('/api/safety/statistics?year=2026')
            ->assertOk();

        $this->assertSame(200000, $res->json('data.man_hours'));
        $this->assertSame(4, $res->json('data.total_incidents'));
        $this->assertSame(3, $res->json('data.total_injuries'));
        $this->assertSame(1, $res->json('data.near_misses'));
        $this->assertSame(2, $res->json('data.lost_time_injuries'));
        $this->assertSame(8, $res->json('data.days_lost'));
        // LTIFR = 2 * 1,000,000 / 200,000 = 10
        $this->assertEquals(10.0, $res->json('data.ltifr'));
        // Severity rate = 8 * 1,000,000 / 200,000 = 40
        $this->assertEquals(40.0, $res->json('data.severity_rate'));
    }

    public function test_rates_are_null_when_no_man_hours_recorded(): void
    {
        $this->incident(['type' => 'injury', 'days_lost' => 2]);

        $res = $this->actingAs($this->actor(['safety.view']))
            ->getJson('/api/safety/statistics?year=2026')
            ->assertOk();

        $this->assertNull($res->json('data.ltifr'));
        $this->assertNull($res->json('data.severity_rate'));
    }

    public function test_recording_man_hours_overwrites_the_same_project_month(): void
    {
        $manager = $this->actor(['safety.manage', 'safety.view']);

        $this->actingAs($manager)->postJson('/api/safety/man-hours', ['year' => 2026, 'month' => 5, 'man_hours' => 1000])->assertOk();
        $this->actingAs($manager)->postJson('/api/safety/man-hours', ['year' => 2026, 'month' => 5, 'man_hours' => 1500])->assertOk();

        $this->assertSame(1, SafetyManHour::where('year', 2026)->where('month', 5)->count());
        $this->assertSame(1500, (int) SafetyManHour::where('year', 2026)->where('month', 5)->value('man_hours'));
    }

    public function test_a_viewer_cannot_record_man_hours(): void
    {
        $this->actingAs($this->actor(['safety.view']))
            ->postJson('/api/safety/man-hours', ['year' => 2026, 'month' => 5, 'man_hours' => 1000])
            ->assertForbidden();
    }
}
