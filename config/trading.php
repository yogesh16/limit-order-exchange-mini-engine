<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Commission Rate
    |--------------------------------------------------------------------------
    |
    | The commission rate applied to all matched trades.
    | Default is 1.5% (0.015) of the trade value.
    |
    */

    'commission_rate' => env('TRADING_COMMISSION_RATE', 0.015),

    /*
    |--------------------------------------------------------------------------
    | Commission Deduction Strategy
    |--------------------------------------------------------------------------
    |
    | Determines who pays the commission on matched trades.
    | Options: 'buyer' or 'seller'
    |
    | - 'buyer': Commission deducted from buyer (buyer receives less asset)
    | - 'seller': Commission deducted from seller (seller receives less USD)
    |
    */

    'commission_from' => env('TRADING_COMMISSION_FROM', 'buyer'),

    /*
    |--------------------------------------------------------------------------
    | Supported Trading Symbols
    |--------------------------------------------------------------------------
    |
    | List of cryptocurrency symbols supported by the exchange.
    | Add new symbols here to enable trading for additional assets.
    |
    */

    'supported_symbols' => [
        'BTC',
        'ETH',
    ],

    /*
    |--------------------------------------------------------------------------
    | Decimal Precision
    |--------------------------------------------------------------------------
    |
    | Precision settings for financial calculations.
    | These values should match the database decimal column definitions.
    |
    */

    'precision' => [
        'price' => 8,       // Price precision (decimal places)
        'amount' => 8,      // Amount precision (decimal places)
        'balance' => 8,     // Balance precision (decimal places)
    ],

    /*
    |--------------------------------------------------------------------------
    | Order Limits
    |--------------------------------------------------------------------------
    |
    | Minimum and maximum limits for order placement.
    | Set to null for no limit.
    |
    */

    'limits' => [
        'min_order_value_usd' => env('TRADING_MIN_ORDER_VALUE', 1.00), // Minimum order value in USD
        'max_order_value_usd' => env('TRADING_MAX_ORDER_VALUE', null), // Maximum order value in USD
    ],

];
