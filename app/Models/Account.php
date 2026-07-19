<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'name',
    'type',
    'currency',
    'balance',
    'credit_limit',
])]
class Account extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        // Enforce user_id creation listener
        static::creating(function (Account $account) {
            if (auth()->check()) {
                $account->user_id = auth()->id();
            }
        });

        // Enforce strict data isolation global scope
        static::addGlobalScope('user_isolation', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('user_id', auth()->id());
            }
        });
    }

    /**
     * Get the user that owns the account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if a credit card transaction of the given amount is within the credit limit.
     */
    public function hasAvailableCredit(float $amount): bool
    {
        if ($this->type !== 'credit_card') {
            return true;
        }

        $currentDebt = abs((float) $this->balance);
        $limit = (float) $this->credit_limit;

        return ($currentDebt + $amount) <= $limit;
    }

    /**
     * Validate and throw validation exception if the expense exceeds available credit.
     *
     * @throws ValidationException
     */
    public function validateExpense(float $amount): void
    {
        if (! $this->hasAvailableCredit($amount)) {
            throw ValidationException::withMessages([
                'balance' => ['Transaction Declined! This expense exceeds your available credit limit.'],
            ]);
        }
    }

    /**
     * Get the transactions for the account (as source account).
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'account_id');
    }

    /**
     * Get the incoming transfers for the account (as destination account).
     */
    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(Transaction::class, 'to_account_id');
    }
}
