<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Standard Working Hours
    |--------------------------------------------------------------------------
    |
    | The number of regular working hours per day. Any hours worked beyond
    | this threshold are counted as overtime.
    |
    */
    'working_hours_per_day' => env('PAYROLL_WORKING_HOURS', 8),

    /*
    |--------------------------------------------------------------------------
    | Overtime Multiplier
    |--------------------------------------------------------------------------
    |
    | The rate multiplier applied to overtime hours. For example, 1.5 means
    | overtime is paid at 150% of the hourly rate.
    |
    */
    'overtime_multiplier' => env('PAYROLL_OVERTIME_MULTIPLIER', 1.5),

    /*
    |--------------------------------------------------------------------------
    | Late Arrival Threshold (minutes)
    |--------------------------------------------------------------------------
    |
    | If an employee clocks in this many minutes after the shift start time,
    | they are marked as "late".
    |
    */
    'late_threshold_minutes' => env('PAYROLL_LATE_THRESHOLD', 15),

    /*
    |--------------------------------------------------------------------------
    | Half-Day Hours Threshold
    |--------------------------------------------------------------------------
    |
    | If total working hours for a day fall below this threshold, the
    | attendance is recorded as "half_day" instead of "present".
    |
    */
    'half_day_hours' => env('PAYROLL_HALF_DAY_HOURS', 4),

    /*
    |--------------------------------------------------------------------------
    | Default Shift Times
    |--------------------------------------------------------------------------
    |
    | Default expected shift start and end times (24h format). Used to
    | determine late arrivals when no custom shift is defined.
    |
    */
    'default_shift_start' => env('PAYROLL_SHIFT_START', '09:00'),
    'default_shift_end' => env('PAYROLL_SHIFT_END', '17:00'),

    /*
    |--------------------------------------------------------------------------
    | Default Base Monthly Salary
    |--------------------------------------------------------------------------
    |
    | Fallback base salary used when a user does not have a salary
    | assigned in the payroll record. Value is in local currency.
    |
    */
    'default_base_salary' => env('PAYROLL_DEFAULT_SALARY', 50000),

    /*
    |--------------------------------------------------------------------------
    | Working Days Per Month
    |--------------------------------------------------------------------------
    |
    | Standard number of working days in a month, used to compute
    | daily and hourly rates from the monthly base salary.
    |
    */
    'working_days_per_month' => env('PAYROLL_WORKING_DAYS', 22),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */
    'currency' => env('PAYROLL_CURRENCY', 'PKR'),

    /*
    |--------------------------------------------------------------------------
    | Absence Deduction
    |--------------------------------------------------------------------------
    |
    | Whether to deduct salary for absent days. If true, each absent day
    | deducts (base_salary / working_days_per_month) from the net salary.
    |
    */
    'deduct_absences' => env('PAYROLL_DEDUCT_ABSENCES', true),

    /*
    |--------------------------------------------------------------------------
    | Excel Column Mapping
    |--------------------------------------------------------------------------
    |
    | Maps the expected column names in the uploaded Excel file to internal
    | field names. Change these if your Excel template uses different headers.
    |
    */
    'excel_columns' => [
        'employee_id' => 'employee_id',
        'date'        => 'date',
        'clock_in'    => 'clock_in',
        'clock_out'   => 'clock_out',
    ],

];
