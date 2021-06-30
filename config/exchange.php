<?php

return [
    'BINANCE' => [
        'apiKey' => env('EXCHANGE_BINANCE_API_KEY'),
        'secretKey' => env('EXCHANGE_BINANCE_SECRET_KEY')
    ],
    'FTX' => [
        'apiKey' => env('EXCHANGE_FTX_API_KEY'),
        'secretKey' => env('EXCHANGE_FTX_SECRET_KEY'),
    ]
];