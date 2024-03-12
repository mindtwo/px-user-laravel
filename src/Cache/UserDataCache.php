<?php

namespace mindtwo\PxUserLaravel\Cache;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use mindtwo\PxUserLaravel\Facades\PxUserSession;
use mindtwo\PxUserLaravel\Http\Client\PxClient;
use mindtwo\PxUserLaravel\Http\Client\PxUserClient;
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

    protected function ttl(): int
    {
        return config('px-user.px_user_cache_time') * 60;
    }

    /**
     * Get cache key.
     */
    protected function cacheKey(): string
    {
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

        if (! isset($this->model->{config('px-user.px_user_id')}) || ! $this->model->tenant_code || ! $this->model->domain_code) {
            return [];
        }

        // TODO: Use user details?
        $client = app()->make(PxClient::class, [
            'tenantCode' => $this->model->tenant_code,
            'domainCode' => $this->model->domain_code,
        ]);

        if ((! $this->model instanceof Authenticatable)) {
            return [];
        }

        $accessTokenHelper = PxUserSession::newAccessTokenHelper($this->model);
        if (! $accessTokenHelper->get('access_token')) {
            return [];
        }

        try {
            $userData = $client->get(PxUserClient::USER, [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessTokenHelper->get('access_token'),
                ],
            ])
                ->json('response');
        } catch (\Throwable $th) {
            Log::error('UserdataCache: '.$th->getMessage());
            $userData = [];
        }

        $userData = $userData['user'] ?? null;

        if (empty($userData)) {
            return [];
        }

        return array_intersect_key($userData, array_flip($this->usedKeys));
    }

    protected function canLoad(): bool
    {
        return isset($this->model->{config('px-user.px_user_id')});
    }

    /**
     * Initialize data cache.
     *
     * @return void
     */
    public static function initialize(array $initialData)
    {
        $key = cache_key('data_cache', [
            'name' => 'user',
            'uuid' => $initialData['id'],
        ])->toString();

        Cache::put($key, array_intersect_key($initialData, array_flip([
            'email',
            'firstname',
            'lastname',
            'is_enabled',
            'is_confirmed',
            'roles',
            'products',
            'preferred_username',
        ])), config('px-user.px_user_cache_time') * 60);
    }
}
