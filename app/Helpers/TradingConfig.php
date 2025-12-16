<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Config;

class TradingConfig
{
    /**
     * Get the commission rate for trades.
     */
    public static function commissionRate(): float
    {
        return (float) Config::get('trading.commission_rate', 0.015);
    }

    /**
     * Get who pays the commission (buyer or seller).
     */
    public static function commissionFrom(): string
    {
        return Config::get('trading.commission_from', 'buyer');
    }

    /**
     * Check if a symbol is supported for trading.
     */
    public static function isSymbolSupported(string $symbol): bool
    {
        return in_array(strtoupper($symbol), self::supportedSymbols());
    }

    /**
     * Get list of supported trading symbols.
     */
    public static function supportedSymbols(): array
    {
        return Config::get('trading.supported_symbols', ['BTC', 'ETH']);
    }

    /**
     * Get precision for price/amount calculations.
     */
    public static function precision(string $type = 'price'): int
    {
        return Config::get("trading.precision.{$type}", 8);
    }

    /**
     * Get minimum order value in USD.
     */
    public static function minOrderValue(): ?float
    {
        $value = Config::get('trading.limits.min_order_value_usd');
        return $value !== null ? (float) $value : null;
    }

    /**
     * Get maximum order value in USD.
     */
    public static function maxOrderValue(): ?float
    {
        $value = Config::get('trading.limits.max_order_value_usd');
        return $value !== null ? (float) $value : null;
    }

    /**
     * Calculate commission for a given USD amount.
     */
    public static function calculateCommission(float $usdAmount): float
    {
        return round($usdAmount * self::commissionRate(), self::precision('balance'));
    }
}
