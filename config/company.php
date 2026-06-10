<?php

return [

    /*
    | NEDS company details for printed quotations and invoices. GSTIN is a
    | placeholder until the real one is set via COMPANY_GSTIN in .env.
    */
    'name' => env('COMPANY_NAME', 'Niranjan Enterprises Digital Solutions'),
    'gstin' => env('COMPANY_GSTIN', '27AAAAA0000A1Z5'),
    'address' => env('COMPANY_ADDRESS', 'Pune, Maharashtra, India'),
    'state' => 'Maharashtra',
    'state_code' => '27',
    'email' => env('COMPANY_EMAIL', 'niranjan.enterprisespune@gmail.com'),
    'phone' => env('COMPANY_PHONE', ''),

];
