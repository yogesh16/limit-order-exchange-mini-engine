<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'balance',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'balance' => 'decimal:8',
        ];
    }

    /**
     * Get the user's assets.
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * Get the user's orders.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Check if user has enough USD balance.
     */
    public function hasEnoughBalance(float $amount): bool
    {
        return bccomp((string) $this->balance, (string) $amount, 8) >= 0;
    }

    /**
     * Deduct USD balance from user.
     * Use within a database transaction for safety.
     */
    public function deductBalance(float $amount): bool
    {
        if (! $this->hasEnoughBalance($amount)) {
            return false;
        }

        $newBalance = bcsub((string) $this->balance, (string) $amount, 8);
        $this->balance = $newBalance;

        return $this->save();
    }

    /**
     * Add USD balance to user.
     */
    public function addBalance(float $amount): bool
    {
        $newBalance = bcadd((string) $this->balance, (string) $amount, 8);
        $this->balance = $newBalance;

        return $this->save();
    }

    /**
     * Get or create an asset for this user.
     */
    public function getOrCreateAsset(string $symbol): Asset
    {
        return $this->assets()->firstOrCreate(
            ['symbol' => strtoupper($symbol)],
            ['amount' => 0, 'locked_amount' => 0]
        );
    }
}
