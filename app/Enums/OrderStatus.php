<?php

namespace App\Enums;

enum OrderStatus: int
{
    case OPEN = 1;
    case FILLED = 2;
    case CANCELLED = 3;

    /**
     * Get the label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::FILLED => 'Filled',
            self::CANCELLED => 'Cancelled',
        };
    }
}
