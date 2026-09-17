<?php

namespace App\Services\MonthlyReport\Sections;

use App\Models\SiteLog;
use App\Services\MonthlyReport\ReportContext;
use Illuminate\Support\Carbon;

final class WeatherBuilder extends AbstractBuilder
{
    public function build(ReportContext $ctx): array
    {
        $logsByDate = SiteLog::forProject($ctx->project->id)
            ->forPeriod($ctx->period->period_start->format('Y-m-d'), $ctx->period->period_end->format('Y-m-d'))
            ->with('weatherEvents')
            ->get()
            ->groupBy(fn (SiteLog $log) => $log->log_date->format('Y-m-d'));

        $days = [];
        $totalMinutes = 0;
        $rainingDays = 0;

        foreach (self::dateRange($ctx->period->period_start, $ctx->period->period_end) as $date) {
            $key = $date->format('Y-m-d');
            $dayLogs = $logsByDate->get($key);

            $events = $dayLogs
                ? $dayLogs->flatMap(fn (SiteLog $log) => $log->weatherEvents)
                    ->map(fn ($e) => ['condition' => $e->condition, 'time' => substr($e->event_time->format('H:i:s'), 0, 5)])
                    ->sortBy('time')
                    ->values()
                    ->all()
                : [];

            $intervals = self::intervals($events);

            if ($intervals !== []) {
                $rainingDays++;
                $totalMinutes += array_sum(array_map(fn ($i) => $i[1] - $i[0], $intervals));
            }

            $days[] = [
                'date' => $key,
                'intervals' => $intervals,
            ];
        }

        return [
            'schema' => 1,
            'days' => $days,
            'summary' => [
                'total_days' => count($days),
                'raining_days' => $rainingDays,
                'raining_hours' => round($totalMinutes / 60, 1),
            ],
        ];
    }

    /**
     * @param  array<int, array{condition:string, time:string}>  $events  ordered by time
     * @return array<int, array{0:int, 1:int}>
     */
    public static function intervals(array $events): array
    {
        $intervals = [];
        $openStart = null;

        foreach ($events as $event) {
            $minutes = self::toMinutes($event['time']);

            if ($event['condition'] === 'rain_start') {
                $openStart = $minutes;

                continue;
            }

            if ($event['condition'] === 'rain_stop') {
                if ($openStart !== null) {
                    $intervals[] = [$openStart, $minutes];
                    $openStart = null;
                }

                continue;
            }
        }

        if ($openStart !== null) {
            $intervals[] = [$openStart, 1440];
        }

        return $intervals;
    }

    private static function toMinutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }

    /** @return array<int, Carbon> */
    private static function dateRange(Carbon $start, Carbon $end): array
    {
        $dates = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates[] = $date->copy();
        }

        return $dates;
    }
}
