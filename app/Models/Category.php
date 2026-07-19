<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string $type
 * @property string|null $icon
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Category extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'icon',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        // A category is visible if it's a shared/system default (user_id null)
        // or owned by the authenticated user — mirrors Account/Transaction's
        // isolation, but must also expose the shared rows those models don't have.
        static::addGlobalScope('visibility', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where(function (Builder $query) {
                    $query->whereNull('user_id')->orWhere('user_id', auth()->id());
                });
            }
        });
    }

    /**
     * Get the user that owns the category.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
