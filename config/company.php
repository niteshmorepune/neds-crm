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

    /*
    | Reply-To shown on customer-facing Visibility Audit emails (payment
    | received, first invite, recovery nudges) -- deliberately Reply-To,
    | not From: those emails still send via the CRM's own authenticated
    | mailer (COMPANY_EMAIL's domain), since contact@niranjanenterprises.com
    | is hosted on a different domain/mail server and sending From: it
    | through the CRM's SMTP would fail SPF/DKIM alignment and likely land
    | in spam. A customer who hits Reply lands in that inbox either way.
    */
    'reply_to_email' => env('COMPANY_REPLY_TO_EMAIL', 'contact@niranjanenterprises.com'),
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

    /*
    | Printed on the invoice PDF's signature line. Blank prints a generic
    | "Authorized Signatory" line with nothing above it -- a normal,
    | GST-compliant default that needs no configuration.
    */
    'signatory_name' => env('COMPANY_SIGNATORY_NAME', ''),

];
