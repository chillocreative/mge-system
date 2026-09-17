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

    /** @return array<int, string> unknown top-level keys, or keys whose override value has the wrong shape */
    public static function validateOverrides(array $data, array $overrides): array
    {
        $invalid = [];
        foreach ($overrides as $key => $value) {
            if ($key === '_rows') {
                if (! array_key_exists('rows', $data)) {
                    $invalid[] = '_rows';

                    continue;
                }
                if (! is_array($value) || ! array_is_list($value)) {
                    $invalid[] = '_rows';
                }

                continue;
            }

            if (! array_key_exists($key, $data)) {
                $invalid[] = (string) $key;

                continue;
            }

            if (! self::sameKind($data[$key], $value)) {
                $invalid[] = (string) $key;
            }
        }

        return $invalid;
    }

    /** An override value must be the same "kind" as the existing data: array vs scalar, and list vs associative map. */
    private static function sameKind(mixed $existing, mixed $value): bool
    {
        $existingIsArray = is_array($existing);
        $valueIsArray = is_array($value);

        if ($existingIsArray !== $valueIsArray) {
            return false;
        }

        if (! $existingIsArray) {
            return true;
        }

        if ($existing === [] || $value === []) {
            return true;
        }

        return array_is_list($existing) === array_is_list($value);
    }
}
