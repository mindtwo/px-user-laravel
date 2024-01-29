<?php

namespace mindtwo\PxUserLaravel\Cache;

use Illuminate\Database\Eloquent\Model;
use mindtwo\TwoTility\Cache\Data\DataCache;

/**
 * @extends DataCache<null|Model>
 */
class AccessTokenCache extends DataCache
{

    public function __construct(
        protected ?Model $model = null,
        protected ?array $tokenData = [],
    ) {
        parent::__construct($model);
    }

    public function cacheData(): array
    {
        return $this->tokenData;
    }

    /**
     * Get cache key.
     */
    protected function cacheKey(): string
    {
        if (! $this->model) {
            return cache_key('access_token', [
                'class' => class_basename($this),
                'auth',
            ]);
        }

        return cache_key('access_token', [
            'class' => class_basename($this),
            'user' => $this->model->id,
            'updated_at' => $this->model->updated_at->timestamp,
        ])->toString();
    }

    /**
     * Get time to live in seconds.
     */
    // protected function ttl(): int
    // {
    //     return $this->;
    // }

}
