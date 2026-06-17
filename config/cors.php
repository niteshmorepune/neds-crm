<?php

return [

    /*
    | CORS applies to the public lead-capture API only (/api/*).
    | The web routes are same-origin so they don't need CORS.
    | Allow the company website to POST leads cross-origin.
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['POST', 'OPTIONS'],

    'allowed_origins' => [
        'https://niranjanenterprises.com',
        'https://www.niranjanenterprises.com',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Lead-Token', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,

];
