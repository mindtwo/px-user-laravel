<?php

namespace mindtwo\PxUserLaravel\Cache;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use mindtwo\PxUserLaravel\Facades\PxUserSession;
use mindtwo\PxUserLaravel\Http\Client\PxUserClient;
use mindtwo\TwoTility\Cache\Data\DataCache;

/**
 * @extends DataCache<Model>
 */
class UserDetailDataCache extends DataCache
{
    private array $usedKeys = [
        'email',
        'firstname',
        'lastname',
        // 'is_enabled',
        // 'is_confirmed',
        'roles',
        'products',
        'preferred_username',
    ];

    protected bool $loadOnAccess = true;

    protected bool $loadOnlyOnce = true;

    protected function ttl(): int
    {
        return config('px-user.px_user_cache_time') * 60;
    }

    /**
     * Get cache key.
     */
    public function cacheKey(): string
    {
        return cache_key('data_cache', [
            'name' => 'user_detail',
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

        if (! $this->checkModel()) {
            return [];
        }

        $client = $client = new PxUserClient(
            tenantCode: $this->model->tenant_code,
            domainCode: $this->model->domain_code
        );

        $accessTokenHelper = PxUserSession::newAccessTokenHelper(auth()->user());
        if (! $accessTokenHelper->get('access_token')) {
            return [];
        }

        // Check if the user is the same as the authenticated user.
        if (Auth::id() === $this->model->id) {
            return array_fill_keys($this->keys(), null);
        }

        try {
            $response = $client->post('users/details', [
                'user_ids' => [$this->model->{config('px-user.px_user_id')}],
            ], [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessTokenHelper->get('access_token'),
                ],
            ])
                ->json('response');
        } catch (\Throwable $th) {
            if (! $th instanceof RequestException || ! in_array($th->response->status(), [401, 403, 404])) {
                throw $th;
            }

            $response = [];
        }

        if (empty($response)) {
            return array_fill_keys($this->keys(), null);
        }

        return array_intersect_key($response[0], array_flip($this->keys()));
    }

    protected function checkModel(): bool
    {
        if (! isset($this->model->{config('px-user.px_user_id')}) || ! $this->model->tenant_code || ! $this->model->domain_code) {
            return false;
        }

        return true;
    }

    protected function canLoad(): bool
    {
        if (! isset($this->model->{config('px-user.px_user_id')})) {
            return false;
        }

        return Auth::id() !== $this->model->id;
    }

    public function keys(): array
    {
        return $this->usedKeys;
    }

    /**
     * Initialize data cache.
     *
     * @return void
     */
    public static function initialize(array $initialData)
    {
        $key = cache_key('data_cache', [
            'name' => 'user_detail',
            'uuid' => $initialData['id'],
        ])->toString();

        Cache::put($key, array_intersect_key($initialData, array_flip([
            'email',
            'firstname',
            'lastname',
            'roles',
            'products',
            'preferred_username',
        ])), config('px-user.px_user_cache_time') * 60);
    }
}
