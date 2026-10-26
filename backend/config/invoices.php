<?php

// Corporate invoices: the seller's details printed on every invoice and the numbering.
return [
    'prefix' => env('INVOICE_PREFIX', 'AP'),
    // Days to pay after the invoice is issued.
    'due_days' => (int) env('INVOICE_DUE_DAYS', 5),
    // Percent of VAT included in the package price; 0 prints no VAT line.
    'vat_rate' => (int) env('INVOICE_VAT_RATE', 0),
    // Unpaid invoices one company may have at a time.
    'open_limit' => 3,
    'seller' => [
        'name' => env('INVOICE_SELLER_NAME', 'Autopass'),
        'identification_code' => env('INVOICE_SELLER_CODE', ''),
        'address' => env('INVOICE_SELLER_ADDRESS', ''),
        'bank_name' => env('INVOICE_SELLER_BANK', ''),
        'bank_code' => env('INVOICE_SELLER_BANK_CODE', ''),
        'bank_account' => env('INVOICE_SELLER_ACCOUNT', ''),
        'email' => env('INVOICE_SELLER_EMAIL', ''),
        'phone' => env('INVOICE_SELLER_PHONE', ''),
    ],
];
