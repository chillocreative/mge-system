<?php

namespace App\Services\ReportData\Programme;

use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ActivityNormaliser
{
    /**
     * Normalise a raw cell/XML value into a Y-m-d date string, or null when it
     * cannot be parsed.
     */
    public static function date(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        if (is_numeric($v)) {
            $num = (float) $v;
            if ($num >= 20000 && $num <= 80000) {
                try {
                    $dt = ExcelDate::excelToDateTimeObject($num);

                    return $dt->format('Y-m-d');
                } catch (\Throwable) {
                    return null;
                }
            }

            return null;
        }

        $value = trim((string) $v);
        if ($value === '') {
            return null;
        }

        // Each candidate format is paired with a shape regex so a loose value
        // (e.g. a 2-digit year) can't be silently accepted by a 4-digit format.
        $formats = [
            'd/m/Y' => '/^\d{1,2}\/\d{1,2}\/\d{4}$/',
            'd/m/y' => '/^\d{1,2}\/\d{1,2}\/\d{2}$/',
            'Y-m-d' => '/^\d{4}-\d{1,2}-\d{1,2}$/',
            'd-M-y' => '/^\d{1,2}-[A-Za-z]{3,}-\d{2}$/',
            'd M Y' => '/^\d{1,2}\s+[A-Za-z]{3,}\s+\d{4}$/',
        ];

        foreach ($formats as $format => $shape) {
            if (! preg_match($shape, $value)) {
                continue;
            }

            $dt = \DateTime::createFromFormat('!'.$format, $value);
            if ($dt !== false) {
                $errors = \DateTime::getLastErrors();
                if ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                    return $dt->format('Y-m-d');
                }
            }
        }

        try {
            $dt = new \DateTime($value);

            return $dt->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Normalise a raw percent value. When $fractionMode is true the value is
     * assumed to be a 0-1 fraction and multiplied by 100. Result is clamped
     * to the 0-100 range. Returns null when the value is not numeric.
     */
    public static function percent(mixed $v, bool $fractionMode): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        $value = is_string($v) ? trim($v) : $v;

        if (is_string($value)) {
            $value = str_replace('%', '', $value);
            $value = str_replace(',', '.', $value);
            $value = trim($value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        $num = (float) $value;

        if ($fractionMode) {
            $num *= 100;
        }

        return max(0.0, min(100.0, $num));
    }

    /**
     * Normalise a raw duration value into a whole number of days, or null
     * when it cannot be parsed.
     */
    public static function durationDays(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }

        if (is_numeric($v)) {
            return (int) round((float) $v);
        }

        $value = trim((string) $v);
        if ($value === '') {
            return null;
        }

        // ISO 8601 duration from MSPDI, e.g. PT40H0M0S. Hours / 8, rounded.
        if (preg_match('/^P(?:T(?:(?<hours>[\d.]+)H)?(?:(?<minutes>[\d.]+)M)?(?:(?<seconds>[\d.]+)S)?)?$/i', $value, $m)) {
            $hours = isset($m['hours']) && $m['hours'] !== '' ? (float) $m['hours'] : 0.0;
            $minutes = isset($m['minutes']) && $m['minutes'] !== '' ? (float) $m['minutes'] : 0.0;
            $seconds = isset($m['seconds']) && $m['seconds'] !== '' ? (float) $m['seconds'] : 0.0;
            $totalHours = $hours + ($minutes / 60) + ($seconds / 3600);

            return (int) round($totalHours / 8);
        }

        // "12 days", "12d", "1.5 wks", "2 weeks", etc.
        if (preg_match('/^([\d.]+)\s*(day|days|d|wk|wks|week|weeks|w)?$/i', $value, $m)) {
            $num = (float) $m[1];
            $unit = strtolower($m[2] ?? 'd');

            if (in_array($unit, ['wk', 'wks', 'week', 'weeks', 'w'], true)) {
                return (int) round($num * 5);
            }

            return (int) round($num);
        }

        return null;
    }

    /**
     * Derive an outline level and trimmed name from a name string's leading
     * indentation, given the indent unit (number of leading spaces per level).
     * A leading tab counts as $unit spaces.
     *
     * @return array{level: int, name: string}
     */
    public static function outlineFromIndent(string $name, int $unit): array
    {
        $unit = max(1, $unit);

        $count = 0;
        $len = strlen($name);
        for ($i = 0; $i < $len; $i++) {
            $char = $name[$i];
            if ($char === ' ') {
                $count++;
            } elseif ($char === "\t") {
                $count += $unit;
            } else {
                break;
            }
        }

        $level = 1 + (int) floor($count / $unit);

        return [
            'level' => $level,
            'name' => trim($name),
        ];
    }

    /**
     * Infer the indentation unit (spaces per level) from a list of raw names,
     * using the smallest non-zero leading-whitespace count found. Defaults to
     * 2 when no name has leading whitespace.
     */
    public static function inferIndentUnit(array $names): int
    {
        $min = null;

        foreach ($names as $name) {
            if (! is_string($name)) {
                continue;
            }

            $count = 0;
            $len = strlen($name);
            for ($i = 0; $i < $len; $i++) {
                $char = $name[$i];
                if ($char === ' ' || $char === "\t") {
                    $count++;
                } else {
                    break;
                }
            }

            if ($count > 0 && ($min === null || $count < $min)) {
                $min = $count;
            }
        }

        return $min ?? 2;
    }

    /**
     * Recompute is_summary for every activity: an activity is a summary when
     * the immediately following activity has a greater outline_level.
     */
    public static function deriveSummaries(array $activities): array
    {
        $count = count($activities);

        foreach ($activities as $i => $activity) {
            $next = $activities[$i + 1] ?? null;
            $isSummary = $next !== null
                && (int) ($next['outline_level'] ?? 1) > (int) ($activity['outline_level'] ?? 1);

            $activities[$i]['is_summary'] = $isSummary;
        }

        return $activities;
    }
}
