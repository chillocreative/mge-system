<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Edit window
    |--------------------------------------------------------------------------
    |
    | How many days after a site log's date it stays editable (plan 20.3). A
    | site log is a dated field record; leaving it editable forever lets history
    | be quietly rewritten. After this window it locks, and only a user with the
    | elevated projects.delete permission can still change it.
    |
    | Set to 0 to disable the window (always editable).
    |
    */
    'edit_window_days' => (int) env('SITELOG_EDIT_WINDOW_DAYS', 7),

];
