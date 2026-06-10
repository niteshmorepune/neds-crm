<?php

return [

    /*
    | Support business hours used to compute ticket SLA due-times. The SLA
    | clock only advances during these hours on working days (timezone =
    | app.timezone, i.e. Asia/Kolkata).
    */

    // ISO weekdays that count as working days (1 = Mon … 7 = Sun). Default Mon–Sat.
    'working_days' => [1, 2, 3, 4, 5, 6],

    // Daily working window, 24h clock.
    'start_hour' => 10,
    'end_hour' => 19,

];
