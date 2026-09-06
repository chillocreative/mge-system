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
    'currency' => env('PAYROLL_CURRENCY', 'RM'),

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
        'date' => 'date',
        'clock_in' => 'clock_in',
        'clock_out' => 'clock_out',
    ],

    /*
    |--------------------------------------------------------------------------
    | Malaysian Statutory Deduction Rates (configurable, editable per payslip)
    |--------------------------------------------------------------------------
    |
    | EPF (KWSP): for employees below 60. Employee contributes 11%. Employer
    | contributes 13% when monthly wage <= RM5,000, otherwise 12%. Contributions
    | are rounded up to the next ringgit per EPF rules.
    |
    | SOCSO & EIS: contribution is capped at the RM6,000 monthly wage ceiling.
    | SOCSO Category 1 (under 60) max = employer RM104.65 + employee RM29.90.
    | EIS = 0.2% employer + 0.2% employee, max RM11.90 each at the ceiling.
    |
    | These are defaults — every payslip allows manual override, and Zakat/PCB
    | are always entered manually. Verify rates against KWSP/PERKESO/LHDN.
    |
    */
    'statutory' => [
        'epf' => [
            'enabled' => env('PAYROLL_EPF_ENABLED', true),
            'employee_rate' => env('PAYROLL_EPF_EMPLOYEE_RATE', 0.11), // 11%
            'employer_rate_low' => env('PAYROLL_EPF_EMPLOYER_LOW', 0.13),  // <= threshold
            'employer_rate_high' => env('PAYROLL_EPF_EMPLOYER_HIGH', 0.12), // > threshold
            'employer_wage_threshold' => env('PAYROLL_EPF_THRESHOLD', 5000),
        ],
        'socso' => [
            'enabled' => env('PAYROLL_SOCSO_ENABLED', true),
            'wage_ceiling' => env('PAYROLL_SOCSO_CEILING', 6000),
            'employer_rate' => env('PAYROLL_SOCSO_EMPLOYER_RATE', 0.0175), // ~1.75% (Cat 1)
            'employee_rate' => env('PAYROLL_SOCSO_EMPLOYEE_RATE', 0.005),  // ~0.5% (Cat 1)
            'employer_max' => env('PAYROLL_SOCSO_EMPLOYER_MAX', 104.65),
            'employee_max' => env('PAYROLL_SOCSO_EMPLOYEE_MAX', 29.90),
        ],
        'eis' => [
            'enabled' => env('PAYROLL_EIS_ENABLED', true),
            'wage_ceiling' => env('PAYROLL_EIS_CEILING', 6000),
            'employer_rate' => env('PAYROLL_EIS_EMPLOYER_RATE', 0.002), // 0.2%
            'employee_rate' => env('PAYROLL_EIS_EMPLOYEE_RATE', 0.002), // 0.2%
            'employer_max' => env('PAYROLL_EIS_EMPLOYER_MAX', 11.90),
            'employee_max' => env('PAYROLL_EIS_EMPLOYEE_MAX', 11.90),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Company details (for payslip & EA Form headers)
    |--------------------------------------------------------------------------
    */
    'company' => [
        'name' => env('COMPANY_NAME', 'Multi Green Engineering Sdn. Bhd.'),
        'reg_no' => env('COMPANY_REG_NO', ''),
        'address' => env('COMPANY_ADDRESS', ''),
        'employer_no' => env('COMPANY_EMPLOYER_NO', ''), // LHDN E number
    ],

];
