<?php

namespace App\Http\Controllers\Concerns;

/**
 * Guards against writing null to NOT NULL database columns.
 *
 * The API's forms post blank fields as empty strings, which the
 * ConvertEmptyStringsToNull middleware turns into null. When a field is
 * validated as `nullable` but its column is NOT NULL (typically with a DB
 * default — number_of_children, base_salary, a status enum, a quantity …),
 * persisting that null throws an integrity-constraint error that surfaces as a
 * 500 "Server Error".
 *
 * Dropping the key when the value is null is the safe normalisation: on create
 * the column falls back to its DB default; on update the stored value is left
 * untouched rather than being nulled or reset.
 */
trait NormalizesNullableColumns
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $columns  NOT NULL columns that must never receive null
     * @return array<string, mixed>
     */
    protected function dropNullColumns(array $data, array $columns): array
    {
        foreach ($columns as $column) {
            if (array_key_exists($column, $data) && $data[$column] === null) {
                unset($data[$column]);
            }
        }

        return $data;
    }
}
