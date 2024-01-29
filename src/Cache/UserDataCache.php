<?php

namespace mindtwo\PxUserLaravel\Cache;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use mindtwo\PxUserLaravel\Http\PxUserClient;
use mindtwo\PxUserLaravel\Services\AccessTokenHelper;
use mindtwo\TwoTility\Cache\Data\DataCache;

/**
 * @extends DataCache<Model>
 */
class UserDataCache extends DataCache
{
    private array $usedKeys = [
        'email',
        'firstname',
        'lastname',
        'is_enabled',
        'is_confirmed',
        'roles',
        'products',
        'preferred_username',
    ];

    /**
     * Create a new cache instance.
     *
     * @param Model $model
     */
    public function __construct(
        protected $model,
        protected ?array $initialData = [],
    ) {
        parent::__construct($model);
    }

    protected function ttl(): int
    {
        return config('px-user.px_user_cache_time') * 60;
    }

    /**
     * Get cache key.
     */
    protected function cacheKey(): string
    {
        if ($this->initialData['id'] ?? false) {
            return cache_key('data_cache', [
                'name' => 'user',
                'uuid' => $this->initialData['id'],
            ])->toString();
        }

        return cache_key('data_cache', [
            'name' => 'user',
            'uuid' => $this->model->{config('px-user.px_user_id')},
        ])->toString();
    }

    /**
     * Get attribute value from data cache.
     *
     * @return array<string, mixed>
     */
    public function cacheData(): array
    {
        if (! empty($this->initialData)) {
            return $this->initialData;
        }

        if (! $this->model->{config('px-user.px_user_id')} || ! $this->model->tenant_code || ! $this->model->domain_code) {
            return [];
        }

        // TODO: Use user details?

        if ((! $this->model instanceof Authenticatable) || ! $token = (new AccessTokenHelper($this->model))->get('access_token')) {
            return [];
        }

        $userData = App::make(PxUserClient::class)->getUserData($token);
        if (empty($userData)) {
            return [];
        }

        return array_intersect_key($userData, array_flip($this->usedKeys));
    }
}
