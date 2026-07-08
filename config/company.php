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
    'whatsapp' => env('COMPANY_WHATSAPP', ''), // E.164 without +, e.g. 919028099919

    /*
    | Fallback Google Calendar appointment-scheduling link shown to clients
    | in the portal when a project's lead assignee/owner hasn't set their own
    | personal link on their Profile page.
    */
    'meet_scheduling_link' => env('COMPANY_MEET_SCHEDULING_LINK', ''),

    /*
    | Bank account details shown on invoices and payment receipts.
    | Set these in .env to override the placeholders.
    */
    'bank_name' => env('COMPANY_BANK_NAME', ''),
    'account_name' => env('COMPANY_ACCOUNT_NAME', ''),
    'account_number' => env('COMPANY_ACCOUNT_NUMBER', ''),
    'ifsc_code' => env('COMPANY_IFSC_CODE', ''),
    'account_type' => env('COMPANY_ACCOUNT_TYPE', 'Current'),
    'upi_id' => env('COMPANY_UPI_ID', ''),

];
