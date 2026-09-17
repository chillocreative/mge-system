<?php

namespace Tests\Unit\MonthlyReport;

use App\Services\MonthlyReport\Sections\WeatherBuilder;
use PHPUnit\Framework\TestCase;

class WeatherBuilderTest extends TestCase
{
    public function test_single_interval(): void
    {
        $events = [
            ['condition' => 'rain_start', 'time' => '09:30'],
            ['condition' => 'rain_stop', 'time' => '11:00'],
        ];

        $this->assertSame([[570, 660]], WeatherBuilder::intervals($events));
    }

    public function test_start_without_stop_closes_at_midnight(): void
    {
        $events = [
            ['condition' => 'rain_start', 'time' => '09:30'],
        ];

        $this->assertSame([[570, 1440]], WeatherBuilder::intervals($events));
    }

    public function test_stop_without_start_is_ignored(): void
    {
        $events = [
            ['condition' => 'rain_stop', 'time' => '11:00'],
        ];

        $this->assertSame([], WeatherBuilder::intervals($events));
    }

    public function test_two_intervals_summed_correctly(): void
    {
        $events = [
            ['condition' => 'rain_start', 'time' => '09:30'],
            ['condition' => 'rain_stop', 'time' => '11:00'],
            ['condition' => 'overcast', 'time' => '12:00'],
            ['condition' => 'rain_start', 'time' => '14:00'],
            ['condition' => 'clear', 'time' => '15:00'],
            ['condition' => 'rain_stop', 'time' => '15:30'],
        ];

        $this->assertSame([[570, 660], [840, 930]], WeatherBuilder::intervals($events));
    }

    public function test_summary_hours_for_ninety_minutes(): void
    {
        $events = [
            ['condition' => 'rain_start', 'time' => '09:00'],
            ['condition' => 'rain_stop', 'time' => '10:30'],
        ];

        $intervals = WeatherBuilder::intervals($events);
        $minutes = array_sum(array_map(fn ($i) => $i[1] - $i[0], $intervals));

        $this->assertSame(1.5, round($minutes / 60, 1));
    }
}
