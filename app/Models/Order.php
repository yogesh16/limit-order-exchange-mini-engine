<?php

namespace App\Models;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'symbol',
        'side',
        'price',
        'amount',
        'status',
        'filled_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'side' => OrderSide::class,
            'status' => OrderStatus::class,
            'price' => 'decimal:8',
            'amount' => 'decimal:8',
            'filled_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter open orders.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::OPEN);
    }

    /**
     * Scope to filter filled orders.
     */
    public function scopeFilled(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::FILLED);
    }

    /**
     * Scope to filter cancelled orders.
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::CANCELLED);
    }

    /**
     * Scope to filter by symbol.
     */
    public function scopeForSymbol(Builder $query, string $symbol): Builder
    {
        return $query->where('symbol', strtoupper($symbol));
    }

    /**
     * Scope to filter buy orders.
     */
    public function scopeBuySide(Builder $query): Builder
    {
        return $query->where('side', OrderSide::BUY);
    }

    /**
     * Scope to filter sell orders.
     */
    public function scopeSellSide(Builder $query): Builder
    {
        return $query->where('side', OrderSide::SELL);
    }

    /**
     * Calculate total USD value of the order.
     */
    public function calculateTotal(): string
    {
        return bcmul((string) $this->price, (string) $this->amount, 8);
    }

    /**
     * Mark order as filled.
     */
    public function markFilled(): bool
    {
        $this->status = OrderStatus::FILLED;
        $this->filled_at = now();

        return $this->save();
    }

    /**
     * Mark order as cancelled.
     */
    public function markCancelled(): bool
    {
        $this->status = OrderStatus::CANCELLED;

        return $this->save();
    }

    /**
     * Check if order is open.
     */
    public function isOpen(): bool
    {
        return $this->status === OrderStatus::OPEN;
    }

    /**
     * Check if order is filled.
     */
    public function isFilled(): bool
    {
        return $this->status === OrderStatus::FILLED;
    }

    /**
     * Check if order is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === OrderStatus::CANCELLED;
    }
}
