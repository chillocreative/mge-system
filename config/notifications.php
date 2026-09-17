<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email channel
    |--------------------------------------------------------------------------
    |
    | Master switch for sending notifications by email. On by default since
    | 2026-09-17: SMTP is configured in Settings > Email (Brevo relay) and the
    | noreply@ sender is verified. Set NOTIFICATIONS_EMAIL_ENABLED=false to fall
    | back to in-app only (for example while rotating SMTP credentials).
    |
    | While this is false the engine sends the in-app (database) channel only,
    | no matter what any per-user preference says.
    |
    */

    'email_enabled' => env('NOTIFICATIONS_EMAIL_ENABLED', true),

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
