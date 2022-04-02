<?php

return [
    'exchanges' => [
        \App\Trade\Exchange\Binance\Spot\Binance::name() => [
            'class'     => \App\Trade\Exchange\Binance\Spot\Binance::class,
            'apiKey'    => env('EXCHANGE_BINANCE_SPOT_API_KEY'),
            'secretKey' => env('EXCHANGE_BINANCE_SPOT_SECRET_KEY')
        ],
        'FTX'                                            => [
            'class'      => \App\Trade\Exchange\FTX\FTX::class,
            'apiKey'     => env('EXCHANGE_FTX_API_KEY'),
            'secretKey'  => env('EXCHANGE_FTX_SECRET_KEY'),
            'subaccount' => env('EXCHANGE_FTX_SUBACCOUNT'),
        ]
    ],

    'indicators' => [
        \App\Trade\Indicator\RSI::class,
        \App\Trade\Indicator\MACD::class,
        \App\Trade\Indicator\Fib::class,
        \App\Trade\Indicator\ATR::class,
        \App\Trade\Indicator\SMA::class,
        \App\Trade\Indicator\EMA::class
    ],

    'strategies' => [
        \App\Trade\Strategy\RSICross::class
    ]
];