<?php

namespace mindtwo\PxUserLaravel\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * External API Token Model
 *
 * Stores OAuth tokens and refresh tokens for external API integrations.
 * Tokens are encrypted at rest and associated with an authenticatable entity
 * via a polymorphic relationship.
 *
 * @property array $token_data
 * @property Carbon|null $valid_until
 * @property string $authenticatable_type
 * @property int $authenticatable_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static Builder<static>|PxUserToken newModelQuery()
 * @method static Builder<static>|PxUserToken newQuery()
 * @method static Builder<static>|PxUserToken query()
 * @method static Builder<static>|PxUserToken forAuthenticatable(Authenticatable $authenticatable)
 * @method static Builder<static>|PxUserToken valid()
 */
class PxUserToken extends Model
{
    use Prunable;

    /**
     * {@inheritDoc}
     */
    protected $fillable = [
        'token_data',
        'valid_until',
        'authenticatable_type',
        'authenticatable_id',
    ];

    /**
     * {@inheritDoc}
     */
    protected $casts = [
        'token_data' => 'encrypted:array',
        'valid_until' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the token data.
     */
    public function token(string $key): mixed
    {
        return data_get($this->token_data, $key);
    }

    public function isValid(): bool
    {
        return $this->valid_until === null || $this->valid_until->isFuture();
    }

    /**
     * Get the prunable model query.
     *
     * Removes tokens that have been expired or revoked for longer than the
     * configured retention period. Tokens without a `valid_until` never expire
     * and are therefore never pruned.
     */
    public function prunable(): Builder
    {
        $retentionDays = (int) config('px-user.token_retention_days', 30);

        return static::query()->where('valid_until', '<=', now()->subDays($retentionDays));
    }

    /**
     * Get the authenticatable that owns the token.
     */
    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include tokens for a specific authenticatable.
     */
    public function scopeForAuthenticatable(Builder $query, Authenticatable $authenticatable): Builder
    {
        return $query->where('authenticatable_type', get_class($authenticatable))
            ->where('authenticatable_id', $authenticatable->getAuthIdentifier());
    }

    /**
     * Scope a query to only include valid tokens.
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereNull('valid_until')
                ->orWhere('valid_until', '>', now());
        });
    }
}
