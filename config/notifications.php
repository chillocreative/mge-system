<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email channel
    |--------------------------------------------------------------------------
    |
    | Master switch for sending notifications by email. Off by default, and it
    | must stay off until SPF/DKIM and the noreply@ mailbox are verified on the
    | production server (go-live plan 27.10, decision AB9 — in-app only for now).
    |
    | While this is false the engine sends the in-app (database) channel only,
    | exactly as before, no matter what any per-user preference says. Turning it
    | on later needs no code change.
    |
    */

    'email_enabled' => env('NOTIFICATIONS_EMAIL_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Email throttle
    |--------------------------------------------------------------------------
    |
    | Maximum emails per user per hour, as a safety net against a bug turning
    | into a flood — and against cPanel's per-hour sending cap, which if exceeded
    | blocks mail for the whole domain, not just this app (27.10.1). In-app
    | notifications are never throttled.
    |
    */

    'email_max_per_hour' => (int) env('NOTIFICATIONS_EMAIL_MAX_PER_HOUR', 30),

];
