<?php

namespace App\Services\MonthlyReport\Export;

use App\Services\MonthlyReport\AttachedPages;

final class OrientationPlanner
{
    public const DEFAULT_LANDSCAPE = ['2.2', '2.3', '2.4', '2.5', '4.1', '4.2'];

    /**
     * Group ordered, included section keys into portrait/landscape chunks.
     *
     * @param  string[]  $orderedIncludedKeys
     * @param  string[]|null  $landscapeOverride
     * @return array<int, array{orientation: string, keys: string[]}>
     */
    public static function plan(array $orderedIncludedKeys, ?array $landscapeOverride): array
    {
        $landscape = $landscapeOverride === null
            ? self::DEFAULT_LANDSCAPE
            : array_values(array_diff($landscapeOverride, ['cover']));

        $chunks = [];
        $current = null;

        foreach ($orderedIncludedKeys as $key) {
            $orientation = in_array($key, $landscape, true) ? 'landscape' : 'portrait';

            if ($current !== null && $current['orientation'] === $orientation) {
                $current['keys'][] = $key;
            } else {
                if ($current !== null) {
                    $chunks[] = $current;
                }
                $current = ['orientation' => $orientation, 'keys' => [$key]];
            }

            // Uploaded attachment pages (2.2/2.4 S-curve charts, 2.5 Gantt chart) are appended
            // right after their section, so force a chunk boundary here even when the following
            // section shares the same orientation.
            if (array_key_exists($key, AttachedPages::KINDS)) {
                $chunks[] = $current;
                $current = null;
            }
        }

        if ($current !== null) {
            $chunks[] = $current;
        }

        return $chunks;
    }
}
