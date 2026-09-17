<?php

namespace App\Services\MonthlyReport;

class SectionMerger
{
    public static function merge(array $data, ?array $overrides): array
    {
        if (! $overrides) {
            return $data;
        }

        if (array_key_exists('_rows', $overrides) && is_array($overrides['_rows'])) {
            $data['rows'] = array_values($overrides['_rows']);
            unset($overrides['_rows']);
        }

        foreach ($overrides as $key => $value) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            if (is_array($value) && is_array($data[$key]) && ! array_is_list($data[$key])) {
                $data[$key] = self::merge($data[$key], $value);
            } else {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /** @return array<int, string> unknown top-level keys */
    public static function validateOverrides(array $data, array $overrides): array
    {
        $unknown = [];
        foreach (array_keys($overrides) as $key) {
            if ($key === '_rows') {
                if (! array_key_exists('rows', $data)) {
                    $unknown[] = '_rows';
                }

                continue;
            }
            if (! array_key_exists($key, $data)) {
                $unknown[] = (string) $key;
            }
        }

        return $unknown;
    }
}
