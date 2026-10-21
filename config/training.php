<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Training Request Notice
    |--------------------------------------------------------------------------
    |
    | HRDF requires the application to be lodged at least 14 days before the
    | training starts, and HR needs time to prepare it. So a request must be
    | filed at least `min_notice_days` before the training date (16 days leaves
    | HR two days to lodge it); `recommended_notice_days` (3 weeks) is what staff
    | are asked to aim for.
    |
    | The company works in Malaysia, but the app clock is UTC — "today" for the
    | notice count is taken in `timezone` so a request filed late evening local
    | time is not counted as the next day.
    |
    */

    'min_notice_days' => (int) env('TRAINING_MIN_NOTICE_DAYS', 16),

    'recommended_notice_days' => (int) env('TRAINING_RECOMMENDED_NOTICE_DAYS', 21),

    'hrdf_notice_days' => 14,

    'timezone' => 'Asia/Kuala_Lumpur',

];
