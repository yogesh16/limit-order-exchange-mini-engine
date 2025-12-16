<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
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
        'amount',
        'locked_amount',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:8',
            'locked_amount' => 'decimal:8',
        ];
    }

    /**
     * Get the user that owns the asset.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if user has enough available amount.
     */
    public function hasEnoughAmount(float $amount): bool
    {
        return bccomp((string) $this->amount, (string) $amount, 8) >= 0;
    }

    /**
     * Lock amount for sell order.
     * Use within a database transaction for safety.
     */
    public function lockAmount(float $amount): bool
    {
        if (! $this->hasEnoughAmount($amount)) {
            return false;
        }

        $this->amount = bcsub((string) $this->amount, (string) $amount, 8);
        $this->locked_amount = bcadd((string) $this->locked_amount, (string) $amount, 8);

        return $this->save();
    }

    /**
     * Release locked amount (e.g., when order is cancelled).
     */
    public function releaseAmount(float $amount): bool
    {
        if (bccomp((string) $this->locked_amount, (string) $amount, 8) < 0) {
            return false;
        }

        $this->locked_amount = bcsub((string) $this->locked_amount, (string) $amount, 8);
        $this->amount = bcadd((string) $this->amount, (string) $amount, 8);

        return $this->save();
    }

    /**
     * Add amount to available balance.
     */
    public function addAmount(float $amount): bool
    {
        $this->amount = bcadd((string) $this->amount, (string) $amount, 8);

        return $this->save();
    }

    /**
     * Deduct from locked amount (e.g., when sell order is filled).
     */
    public function deductLockedAmount(float $amount): bool
    {
        if (bccomp((string) $this->locked_amount, (string) $amount, 8) < 0) {
            return false;
        }

        $this->locked_amount = bcsub((string) $this->locked_amount, (string) $amount, 8);

        return $this->save();
    }

    /**
     * Get total amount (available + locked).
     */
    public function getTotalAmount(): string
    {
        return bcadd((string) $this->amount, (string) $this->locked_amount, 8);
    }
}
