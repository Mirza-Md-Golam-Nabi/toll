<?php

return [

    /*
    | booth   = installed on the toll booth PC (local MySQL, works offline, pushes data).
    | central = online server that receives data from booths and serves reports.
    */
    'mode' => env('TOLL_MODE', 'booth'),

    /* Code of the booth this installation represents (booth mode). */
    'booth_code' => env('TOLL_BOOTH_CODE', 'B01'),

    'sync' => [
        'central_url' => env('TOLL_CENTRAL_URL'),
        'token' => env('TOLL_SYNC_TOKEN'),
        'batch_size' => (int) env('TOLL_SYNC_BATCH', 200),
        'timeout' => (int) env('TOLL_SYNC_TIMEOUT', 20),
    ],

    'printer' => [
        // escpos = print directly to the thermal printer, browser = open a printable receipt page.
        'driver' => env('TOLL_PRINTER_DRIVER', 'browser'),
        // windows (shared printer name) | network (IP) | file (device path / debug file)
        'connector' => env('TOLL_PRINTER_CONNECTOR', 'windows'),
        'target' => env('TOLL_PRINTER_TARGET', 'POS58'),
        'port' => (int) env('TOLL_PRINTER_PORT', 9100),
        'chars_per_line' => (int) env('TOLL_PRINTER_CHARS', 32),
    ],

    /* Editable from the Settings page; these are the fallbacks. */
    'defaults' => [
        'bridge_name_bn' => 'সৈয়দ মাছ-উদ রুমী সেতু',
        'bridge_name_en' => 'Syed Mash-ud Rumi Bridge',
        'receipt_header' => 'TOLL RECEIPT',
        'receipt_footer' => 'Thank you. Have a safe journey.',
        'helpline' => '',
        'currency' => '৳',
    ],
];
