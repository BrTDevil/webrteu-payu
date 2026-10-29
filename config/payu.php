<?php

declare(strict_types=1);

return [

    /*
    | payu-gpo (conturi noi) | payu-ro (conturi vechi, începute înainte de 4 nov. 2024, sau volum mare). Întreabă PayU.
    */
    'driver' => env('PAYU_DRIVER', 'payu-gpo'),

    'sandbox' => (bool) env('PAYU_SANDBOX', true),

    'ro' => [
        'merchant' => env('PAYU_RO_MERCHANT'),
        'secret' => env('PAYU_RO_SECRET'),
        'base_url' => env('PAYU_RO_BASE_URL', 'https://secure.payu.ro'),
        'payment_method' => env('PAYU_RO_PAYMENT_METHOD', 'CCVISAMC'),
    ],

    'gpo' => [
        'pos_id' => env('PAYU_GPO_POS_ID'),
        'second_key' => env('PAYU_GPO_SECOND_KEY'),
        'client_id' => env('PAYU_GPO_CLIENT_ID'),
        'client_secret' => env('PAYU_GPO_CLIENT_SECRET'),

        /*
        | null = https://secure.snd.payu.com (sandbox) sau https://secure.payu.com (producție).
        */
        'base_url' => env('PAYU_GPO_BASE_URL'),
    ],

    'timeout' => (int) env('PAYU_TIMEOUT', 20),

    /*
    | null = route('payments.webhook', 'payu').
    */
    'notify_url' => env('PAYU_NOTIFY_URL'),

];
