<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Leave Policy Engine
    |--------------------------------------------------------------------------
    |
    | Master switch for the policy-driven leave calculation engine (work
    | patterns, public holidays, entitlement tiers, quota pools).
    |
    | This defaults to FALSE on purpose. MGE-PMS is already live with real staff
    | data, and turning the engine on changes the number of days deducted from a
    | leave request and the entitlement each employee sees. Per the go-live plan
    | (§27.14), that switch is flipped only after:
    |
    |   1. HR has confirmed the entitlement tiers (they are seeded from
    |      Employment Act minimums and flagged is_seed_default until reviewed),
    |   2. HR has entered the gazetted public holidays for the year, and
    |   3. the shadow-mode recalculation has been compared against the current
    |      numbers with every difference understood.
    |
    | While this is false, leave behaves exactly as it does today.
    |
    */

    'engine_enabled' => env('LEAVE_ENGINE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Holiday Scope
    |--------------------------------------------------------------------------
    |
    | The state whose gazetted holidays apply to staff, alongside national ones.
    | MGE operates out of Penang. Holidays recorded for any other state are
    | ignored by the calculator.
    |
    */

    'holiday_state' => env('LEAVE_HOLIDAY_STATE', 'Penang'),

];
