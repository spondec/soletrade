<?php

return [
    'exchanges' => [
        \App\Trade\Exchange\Binance\Spot\Binance::name() => [
            'class'     => \App\Trade\Exchange\Binance\Spot\Binance::class,
            'apiKey'    => env('EXCHANGE_BINANCE_SPOT_API_KEY'),
            'secretKey' => env('EXCHANGE_BINANCE_SPOT_SECRET_KEY'),
        ],
        'FTX'                                            => [
            'class'      => \App\Trade\Exchange\FTX\FTX::class,
            'apiKey'     => env('EXCHANGE_FTX_API_KEY'),
            'secretKey'  => env('EXCHANGE_FTX_SECRET_KEY'),
            'subaccount' => env('EXCHANGE_FTX_SUBACCOUNT'),
        ],
    ],

    'telegram' => [
        'token'    => env('TELEGRAM_BOT_TOKEN'),
        'name'     => env('TELEGRAM_BOT_NAME'),
        'password' => env('TELEGRAM_BOT_PASSWORD'), // optional, if set, bot will be authenticated
    ],

    'options' => [

        'concurrentProcesses' => env('CONCURRENT_PROCESSES', 8),

        'recoverableRequest' => [
            'retryInSeconds' => 5,
            'retryLimit'     => 5,
            'handle'         => [
                \ccxt\NetworkError::class,
            ],
        ],
    ],
];
