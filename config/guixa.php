<?php

return [

    /*
    | The cooperative's timezone: every local date and time in the specs (cutoffs, pickup dates)
    | is read in it. Instants are stored in UTC.
    */
    'timezone' => env('COOP_TIMEZONE', 'Europe/Madrid'),

];
